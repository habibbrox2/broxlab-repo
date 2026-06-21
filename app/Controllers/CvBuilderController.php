<?php

/**
 * app/Controllers/CvBuilderController.php
 * 
 * CV Builder Controller — builder API, section/item management, personal info.
 * Extracted from CvController for focused separation.
 */

class CvBuilderController
{
    // BUILDER API

    public static function saveBuilderStep(string $id): void
    {
        $userId = requireAuth();
        $id = (int)$id;
        global $mysqli;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, $userId)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['step'])) { jsonResponse(['error' => 'Step name is required'], 400); return; }
        $step = sanitize_input($input['step']);
        $sd = $input['data'] ?? [];
        array_walk_recursive($sd, function (&$v) { if (is_string($v)) $v = sanitize_input($v); });
        jsonResponse($cvModel->saveBuilderStep($id, $step, $sd) ? ['success' => true, 'message' => 'Step saved'] : ['error' => 'Failed to save step']);
    }

    public static function builderProgress(string $id): void
    {
        $userId = requireAuth();
        $id = (int)$id;
        global $mysqli;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, $userId)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $d = $cvModel->getBuilderData($id);
        $steps = ['personal','summary','experience','education','skills','languages','social_links','custom_sections','references'];
        $p = [];
        foreach ($steps as $s) {
            $v = $d[$s] ?? [];
            $p[$s] = $s === 'skills' ? (!empty($v['technical'])||!empty($v['soft'])) : (in_array($s,['languages','social_links','custom_sections','references']) ? is_array($v)&&count($v)>0 : !empty($v));
        }
        jsonResponse(['success' => true, 'progress' => $p, 'total_steps' => count($steps), 'completed_steps' => count(array_filter($p))]);
    }

    public static function completeBuilder(string $id): void
    {
        global $mysqli;
        $userId = requireAuth();
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        $cvSectionModel = new CvSectionModel($mysqli);
        $cvItemModel = new CvItemModel($mysqli);
        $cvVersionModel = new CvVersionModel($mysqli);
        if (!$cvModel->belongsToUser($id, $userId)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $input = json_decode(file_get_contents('php://input'), true);
        $requestedTemplate = is_array($input) ? ($input['template'] ?? null) : null;
        $data = $cvModel->getBuilderData($id);
        if (empty($data)) { jsonResponse(['error' => 'No builder data found'], 400); return; }
        try { $cvVersionModel->createVersion($id, $userId); } catch (Throwable $e) {}
        if ($requestedTemplate !== null) {
            $resolvedTemplate = cvResolveTemplate(
                is_string($requestedTemplate) ? $requestedTemplate : null,
                $data['_template'] ?? null,
                cvGetTemplateAllowlist(),
                'modern'
            );
            $cvModel->update($id, ['template' => $resolvedTemplate]);
            $data['_template'] = $resolvedTemplate;
        }
        if (!empty($data['personal']['full_name'])) $cvModel->update($id, ['title' => sanitize_input($data['personal']['full_name']) . "'s CV"]);
        cvMaterializeBuilderData($cvSectionModel, $cvItemModel, $id, $data);
        $cvModel->update($id, ['is_active'=>1, 'builder_data'=>null]);
        logActivity("CV Builder Completed", "cv", $id, [], 'success');
        jsonResponse(['success'=>true,'message'=>'CV completed successfully!','redirect'=>'/cv-builder/'.$id]);
    }

    // PERSONAL INFO API

    public static function apiGetPersonalInfo(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel = new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        try {
            $d=(new CvPersonalInfoModel($mysqli))->getByCvId($id);
            jsonResponse($d?['success'=>true,'data'=>['full_name'=>$d['full_name']??'','job_title'=>$d['job_title']??'','email'=>$d['email']??'','phone'=>$d['phone']??'','address'=>$d['address']??'','date_of_birth'=>$d['date_of_birth']??'','nationality'=>$d['nationality']??'','gender'=>$d['gender']??'','driving_license'=>$d['driving_license']??'','website'=>$d['website']??'','linkedin'=>$d['linkedin']??'','github'=>$d['github']??'','twitter'=>$d['twitter']??'','portfolio'=>$d['portfolio']??'','national_id_no'=>$d['national_id_no']??'','passport_no'=>$d['passport_no']??'','birth_certificate_no'=>$d['birth_certificate_no']??'','religion'=>$d['religion']??'']]:['success'=>true,'data'=>null]);
        }catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Failed to load personal info'],500);}
    }

    public static function apiSavePersonalInfo(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel = new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $input=json_decode(file_get_contents('php://input'),true);
        if(!is_array($input)){jsonResponse(['error'=>'Invalid request body'],400);return;}
        try {
            array_walk_recursive($input,function(&$v){if(is_string($v))$v=sanitize_input($v);});
            jsonResponse((new CvPersonalInfoModel($mysqli))->save($id,$userId,$input)?['success'=>true,'message'=>'Personal info saved']:['error'=>'Failed to save personal info']);
        }catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Save failed: '.$e->getMessage()],500);}
    }

    // SECTION MANAGEMENT

    public static function createSection(string $cvId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $d=json_decode(file_get_contents('php://input'),true);
        $sid=$cvSectionModel->create($cvId,$d['section_type']??'summary',$d['title']??'New Section');
        jsonResponse($sid?['success'=>true,'section_id'=>$sid]:['error'=>'Failed to create section']);
    }

    public static function updateSection(string $cvId, string $sectionId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $sectionId=(int)$sectionId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        if(!$cvSectionModel->belongsToSection($sectionId,$cvId)){jsonResponse(['error'=>'Section not found'],404);}
        jsonResponse($cvSectionModel->update($sectionId,json_decode(file_get_contents('php://input'),true))?['success'=>true]:['error'=>'Failed to update section']);
    }

    public static function deleteSection(string $cvId, string $sectionId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $sectionId=(int)$sectionId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        if(!$cvSectionModel->belongsToSection($sectionId,$cvId)){jsonResponse(['error'=>'Section not found'],404);}
        jsonResponse($cvSectionModel->delete($sectionId)?['success'=>true]:['error'=>'Failed to delete section']);
    }

    public static function reorderSections(string $cvId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $ids=json_decode(file_get_contents('php://input'),true)['section_ids']??[];
        jsonResponse($cvSectionModel->reorder($cvId,$ids)?['success'=>true]:['error'=>'Failed to reorder sections']);
    }

    // ITEM MANAGEMENT

    public static function createItem(string $cvId, string $sectionId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $sectionId=(int)$sectionId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        if(!$cvSectionModel->belongsToSection($sectionId,$cvId)){jsonResponse(['error'=>'Section not found'],404);}
        $d=json_decode(file_get_contents('php://input'),true);
        $iid=$cvItemModel->create($sectionId,$d['item_type']??'generic',$d['content']??[]);
        jsonResponse($iid?['success'=>true,'item_id'=>$iid]:['error'=>'Failed to create item']);
    }

    public static function updateItem(string $cvId, string $sectionId, string $itemId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $sectionId=(int)$sectionId;
        $itemId=(int)$itemId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        if(!$cvSectionModel->belongsToSection($sectionId,$cvId)){jsonResponse(['error'=>'Section not found'],404);}
        if(!$cvItemModel->belongsToSection($itemId,$sectionId)){jsonResponse(['error'=>'Item not found'],404);}
        $d=json_decode(file_get_contents('php://input'),true);
        if(isset($d['content'])&&is_array($d['content'])){$ex=$cvItemModel->getById($itemId);$d['content']=cvMergeContent(is_array($ex['content']??null)?$ex['content']:[],$d['content']);}
        jsonResponse($cvItemModel->update($itemId,$d)?['success'=>true]:['error'=>'Failed to update item']);
    }

    public static function deleteItem(string $cvId, string $sectionId, string $itemId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $sectionId=(int)$sectionId;
        $itemId=(int)$itemId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        if(!$cvSectionModel->belongsToSection($sectionId,$cvId)){jsonResponse(['error'=>'Section not found'],404);}
        if(!$cvItemModel->belongsToSection($itemId,$sectionId)){jsonResponse(['error'=>'Item not found'],404);}
        jsonResponse($cvItemModel->delete($itemId)?['success'=>true]:['error'=>'Failed to delete item']);
    }

    public static function reorderItems(string $cvId, string $sectionId): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvId=(int)$cvId;
        $sectionId=(int)$sectionId;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        if(!$cvModel->belongsToUser($cvId,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        if(!$cvSectionModel->belongsToSection($sectionId,$cvId)){jsonResponse(['error'=>'Section not found'],404);}
        $ids=json_decode(file_get_contents('php://input'),true)['item_ids']??[];
        jsonResponse($cvItemModel->reorder($sectionId,$ids)?['success'=>true]:['error'=>'Failed to reorder items']);
    }
}
