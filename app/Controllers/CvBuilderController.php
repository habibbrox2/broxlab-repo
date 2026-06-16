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
        $steps = ['personal','summary','experience','education','skills','languages','certificates','projects','social_links','custom_sections','references'];
        $p = [];
        foreach ($steps as $s) {
            $v = $d[$s] ?? [];
            $p[$s] = $s === 'skills' ? (!empty($v['technical'])||!empty($v['soft'])) : (in_array($s,['languages','certificates','projects','social_links','custom_sections','references']) ? is_array($v)&&count($v)>0 : !empty($v));
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
        $data = $cvModel->getBuilderData($id);
        if (empty($data)) { jsonResponse(['error' => 'No builder data found'], 400); return; }
        try { $cvVersionModel->createVersion($id, $userId); } catch (Throwable $e) {}
        if (!empty($data['personal']['full_name'])) $cvModel->update($id, ['title' => sanitize_input($data['personal']['full_name']) . "'s CV"]);
        try { foreach ($cvSectionModel->getByCvId($id) as $s) $cvSectionModel->delete($s['id']); } catch (Throwable $e) {}
        $map = ['summary'=>['title'=>'Summary','steps'=>['personal','summary']],'experience'=>['title'=>'Work Experience','steps'=>['experience']],'education'=>['title'=>'Education','steps'=>['education']],'skills'=>['title'=>'Skills','steps'=>['skills']],'languages'=>['title'=>'Languages','steps'=>['languages']],'projects'=>['title'=>'Projects','steps'=>['projects']],'certifications'=>['title'=>'Certifications','steps'=>['certificates']],'social_links'=>['title'=>'Social Links','steps'=>['social_links']],'custom_sections'=>['title'=>'Custom Sections','steps'=>['custom_sections']],'references'=>['title'=>'References','steps'=>['references']]];
        foreach ($map as $st => $cfg) {
            $hd = false; foreach ($cfg['steps'] as $sp) { if (!empty($data[$sp])) { $hd = true; break; } } if (!$hd) continue;
            $sid = $cvSectionModel->create($id, $st, $cfg['title']); if (!$sid) continue;
            switch ($st) {
                case 'summary':
                    $c = array_merge($data['personal']??[], ['summary'=>($data['summary']['professional_summary']??''),'objective'=>($data['summary']['career_objective']??''),'job_title'=>($data['summary']['job_title']??'')]);
                    if (!empty(array_filter($c))) $cvItemModel->create($sid, 'summary', $c); break;
                case 'experience':
                    foreach (($data['experience']??[]) as $e) { if (!empty($e['company'])) $cvItemModel->create($sid, 'experience', ['company'=>sanitize_input($e['company']??''),'position'=>sanitize_input($e['position']??''),'location'=>sanitize_input($e['location']??''),'start_date'=>sanitize_input($e['start_date']??''),'end_date'=>sanitize_input($e['end_date']??''),'is_current'=>!empty($e['is_current'])?1:0,'description'=>sanitize_input($e['responsibilities']??$e['description']??'')]); } break;
                case 'education':
                    foreach (($data['education']??[]) as $e) { if (!empty($e['institution'])) $cvItemModel->create($sid, 'education', ['institution'=>sanitize_input($e['institution']??''),'degree'=>sanitize_input($e['degree']??''),'field'=>sanitize_input($e['field']??''),'start_date'=>sanitize_input($e['start_year']??$e['start_date']??''),'end_date'=>sanitize_input($e['end_year']??$e['end_date']??''),'gpa'=>sanitize_input($e['gpa']??'')]); } break;
                case 'skills':
                    $tech = ($data['skills']['technical']??[]); $soft = ($data['skills']['soft']??[]);
                    if (!empty($tech)||!empty($soft)) {
                        $all = []; foreach ((array)$tech as $s) { if (!empty(trim($s))) $all[] = sanitize_input(trim($s)); } foreach ((array)$soft as $s) { if (!empty(trim($s))) $all[] = sanitize_input(trim($s)); }
                        $cvItemModel->create($sid, 'skills', ['skills'=>$all,'technical'=>$tech,'soft'=>$soft]);
                    } break;
                case 'languages':
                    foreach (($data['languages']??[]) as $l) { if (!empty($l['name'])) $cvItemModel->create($sid, 'language', ['name'=>sanitize_input($l['name']??''),'proficiency'=>sanitize_input($l['proficiency']??'intermediate')]); } break;
                case 'social_links':
                    foreach (($data['social_links']??[]) as $l) { if (!empty($l['url'])) $cvItemModel->create($sid, 'social_link', ['platform'=>sanitize_input($l['platform']??''),'url'=>sanitize_input($l['url']??'')]); } break;
                case 'custom_sections':
                    foreach (($data['custom_sections']??[]) as $s) { if (!empty($s['title'])) $cvItemModel->create($sid, 'custom_section', ['title'=>sanitize_input($s['title']??''),'content'=>sanitize_input($s['content']??'')]); } break;
                case 'projects':
                    foreach (($data['projects']??[]) as $p) { if (!empty($p['name'])) $cvItemModel->create($sid, 'project', ['name'=>sanitize_input($p['name']??''),'description'=>sanitize_input($p['description']??''),'technologies'=>sanitize_input($p['technologies']??''),'url'=>sanitize_input($p['url']??'')]); } break;
                case 'certifications':
                    foreach (($data['certificates']??[]) as $c) { if (!empty($c['name'])) $cvItemModel->create($sid, 'certification', ['name'=>sanitize_input($c['name']??''),'issuer'=>sanitize_input($c['organization']??$c['issuer']??''),'date'=>sanitize_input($c['issue_date']??$c['date']??'')]); } break;
                case 'references':
                    foreach (($data['references']??[]) as $r) { if (!empty($r['name'])) $cvItemModel->create($sid, 'reference', ['name'=>sanitize_input($r['name']??''),'title'=>sanitize_input($r['title']??''),'email'=>sanitize_input($r['email']??''),'phone'=>sanitize_input($r['phone']??''),'company'=>sanitize_input($r['company']??'')]); } break;
            }
        }
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
