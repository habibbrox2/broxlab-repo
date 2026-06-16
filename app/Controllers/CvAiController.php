<?php
class CvAiController {
    public static function aiCoverLetter(string $id): void {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        $cvRateLimitModel=new CvRateLimitModel($mysqli);
        try{$rl=$cvRateLimitModel->checkRateLimit($userId,'ai_cover_letter');if(!$rl['allowed']){jsonResponse(['error'=>'Rate limit exceeded. You can generate '.$rl['remaining'].' more cover letters.','remaining'=>$rl['remaining'],'reset_at'=>$rl['reset_at']],429);return;}}catch(Exception$e){$rl=['remaining'=>999,'reset_at'=>time()+3600];}
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $input=json_decode(file_get_contents('php://input'),true);
        $company=sanitize_input($input['company_name']??'');$job=sanitize_input($input['job_title']??'');$desc=sanitize_input($input['job_description']??'');
        if(empty($company)||empty($job)){jsonResponse(['error'=>'Company name and job title are required'],400);return;}
        $sections=$cvSectionModel->getByCvId($id);$cvData=['summary'=>'','experience'=>[],'education'=>[],'skills'=>[],'personal'=>[]];
        foreach($sections as$sec){$items=$cvItemModel->getBySectionId($sec['id']);switch($sec['section_type']){case'summary':$c=$items[0]['content']??[];$cvData['summary']=$c['summary']??$c['text']??'';$cvData['personal']=['full_name'=>$c['full_name']??$c['name']??''];break;case'experience':$cvData['experience']=array_map(fn($i)=>$i['content'],$items);break;case'education':$cvData['education']=array_map(fn($i)=>$i['content'],$items);break;case'skills':$cvData['skills']=array_map(fn($i)=>$i['content'],$items);break;}}
        require_once dirname(__DIR__,1).'/Helpers/CvAiHelper.php';
        $result=(new CvAiHelper($mysqli))->generateCoverLetter($cvData,$company,$job,$desc);
        logActivity("Cover Letter Generated","cv",$id,['company'=>$company,'job_title'=>$job],'success');
        jsonResponse($result);
    }

    public static function aiImprove(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvRateLimitModel=new CvRateLimitModel($mysqli);
        try{$rl=$cvRateLimitModel->checkRateLimit($userId,'ai_improve');if(!$rl['allowed']){jsonResponse(['error'=>'Rate limit exceeded. You have '.$rl['remaining'].' improvements remaining.','remaining'=>$rl['remaining'],'reset_at'=>$rl['reset_at']],429);return;}}catch(Exception$e){$rl=['remaining'=>999,'reset_at'=>time()+3600];}
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $d=json_decode(file_get_contents('php://input'),true);
        require_once dirname(__DIR__,1).'/Helpers/CvAiHelper.php';
        jsonResponse((new CvAiHelper($mysqli))->improveText($d['text']??'',$d['type']??'bullet'));
    }

    public static function aiAtsScore(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        $cvRateLimitModel=new CvRateLimitModel($mysqli);
        try{$rl=$cvRateLimitModel->checkRateLimit($userId,'ai_ats_score');if(!$rl['allowed']){jsonResponse(['error'=>'Rate limit exceeded','remaining'=>$rl['remaining'],'reset_at'=>$rl['reset_at']],429);}}catch(Exception$e){$rl=['remaining'=>999,'reset_at'=>time()+3600];}
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $sections=$cvSectionModel->getByCvId($id);$cvData=['summary'=>'','experience'=>[],'education'=>[],'skills'=>[]];
        foreach($sections as$sec){$items=$cvItemModel->getBySectionId($sec['id']);switch($sec['section_type']){case'summary':$cvData['summary']=$items[0]['content']['text']??'';break;case'experience':$cvData['experience']=array_map(fn($i)=>$i['content'],$items);break;case'education':$cvData['education']=array_map(fn($i)=>$i['content'],$items);break;case'skills':$cvData['skills']=array_map(fn($i)=>$i['content']['name']??'',$items);break;}}
        require_once dirname(__DIR__,1).'/Helpers/CvAiHelper.php';
        $result=(new CvAiHelper($mysqli))->calculateAtsScore($cvData);
        header('X-RateLimit-Remaining: '.$rl['remaining']);header('X-RateLimit-Reset: '.$rl['reset_at']);
        jsonResponse($result);
    }

    public static function bulkDelete(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvModel=new CvModel($mysqli);
        $cvShareModel=new CvShareModel($mysqli);
        $cvVersionModel=new CvVersionModel($mysqli);
        $d=json_decode(file_get_contents('php://input'),true);
        $ids=$d['cv_ids']??[];
        if(empty($ids)){jsonResponse(['error'=>'No CV IDs provided'],400);}
        $deleted=[];$failed=[];
        foreach($ids as$id){$id=(int)$id;if(!$cvModel->belongsToUser($id,$userId)){$failed[]=['id'=>$id,'reason'=>'Forbidden'];continue;}
            try{$cvVersionModel->createVersion($id,$userId);$cvVersionModel->pruneVersions($id,10);}catch(Throwable$e){}
            $cvShareModel->deleteByCvId($id);
            if($cvModel->delete($id)){$deleted[]=$id;logActivity("CV Bulk Deleted","cv",$id,[],'success');}else{$failed[]=['id'=>$id,'reason'=>'Delete failed'];}
        }
        jsonResponse(['success'=>true,'deleted'=>$deleted,'failed'=>$failed,'total_deleted'=>count($deleted),'total_failed'=>count($failed)]);
    }

    public static function bulkExport(): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        $d=json_decode(file_get_contents('php://input'),true);
        $ids=$d['cv_ids']??[];
        $template=$d['template']??'modern';
        if(empty($ids)){jsonResponse(['error'=>'No CV IDs provided'],400);}
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($template,null,$t,'modern');
        $exports=[];
        foreach($ids as$id){$id=(int)$id;if(!$cvModel->belongsToUser($id,$userId))continue;
            $cv=$cvModel->getById($id);$sections=$cvSectionModel->getByCvId($id);
            foreach($sections as&$s)$s['items']=$cvItemModel->getBySectionId($s['id']);
            $visible=array_filter($sections,fn($s)=>$s['is_visible']);
            $exports[]=['cv_id'=>$id,'title'=>$cv['title'],'html'=>$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible])];
        }
        jsonResponse(['success'=>true,'exports'=>$exports,'total'=>count($exports)]);
    }
}
