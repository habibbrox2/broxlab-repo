<?php
class CvExportController {
    public static function apiPreview(string $id): void {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $cv=$cvModel->getById($id);
        $sections=$cvSectionModel->getByCvId($id);
        foreach($sections as&$s)$s['items']=$cvItemModel->getBySectionId($s['id']);
        $visible=array_values(array_filter($sections,fn($s)=>$s['is_visible']));
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($_GET['template']??null,$cv['template']??null,$t,'modern');
        $zoom=max(0.5,min(2.0,(float)($_GET['zoom']??1.0)));
        try{$html=$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible]);}catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Render failed: '.$e->getMessage()],500);return;}
        header('Content-Type:text/html;charset=utf-8');
        echo cvRenderA4PreviewHtml($html,$slug,$cv['id'],$zoom,(int)($cv['completion_score']??0));
        exit;
    }

    public static function preview(string $id): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $cv=$cvModel->getById($id);
        $sections=$cvSectionModel->getByCvId($id);
        foreach($sections as&$s)$s['items']=$cvItemModel->getBySectionId($s['id']);
        $visible=array_filter($sections,fn($s)=>$s['is_visible']);
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($_GET['template']??null,$cv['template']??null,$t,'modern');
        $zoom=max(0.5,min(2.0,(float)($_GET['zoom']??1.0)));
        try{$html=$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible]);}catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Render failed: '.$e->getMessage()],500);return;}
        if(!empty($_GET['print'])){echo$html;exit;}
        echo cvRenderA4PreviewHtml($html,$slug,$cv['id'],$zoom,(int)($cv['completion_score']??0));
        exit;
    }

    public static function redirectExport(string $id): void
    {
        header('Location: /cv-builder/'.(int)$id.'/export/pdf');
        exit;
    }

    public static function exportPdf(string $id): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        $cvAnalyticsModel=new CvAnalyticsModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){http_response_code(403);echo'Forbidden';exit;}
        $cv=$cvModel->getById($id);
        $sections=$cvSectionModel->getByCvId($id);
        foreach($sections as&$s)$s['items']=$cvItemModel->getBySectionId($s['id']);
        $visible=array_filter($sections,fn($s)=>$s['is_visible']);
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($_GET['template']??null,$cv['template']??null,$t,'modern');
        try{$cvAnalyticsModel->trackEvent($id,'download',['source'=>'export','template'=>$slug]);}catch(Throwable$e){}
        $html=$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible]);
        require_once dirname(__DIR__,1).'/Helpers/MpdfHelper.php';
        $pdfTitle=$cv['title']??'CV';
        $pdfFilename=preg_replace('/[^a-zA-Z0-9_\\-\\x{0980}-\\x{09FF}]/u','_',$pdfTitle).'.pdf';
        if(ob_get_level()>0)ob_clean();
        $mpdfConfig=['format'=>[210,297],'margin_left'=>15,'margin_right'=>15,'margin_top'=>20,'margin_bottom'=>25,'margin_header'=>5,'margin_footer'=>10,'orientation'=>'P','dpi'=>300,'img_dpi'=>300,'use_kwt'=>true,'use_substitutions'=>true,'compress'=>true];
        $mpdf=mpdf_create_instance($mpdfConfig);
        if(!$mpdf){http_response_code(500);echo'Failed to initialize PDF engine';exit;}
        try {
            mpdf_apply_runtime_optimizations($mpdf);
            $mpdf->SetTitle($pdfTitle);$mpdf->SetAuthor('BroxLab CV Builder');$mpdf->SetSubject('Curriculum Vitae');$mpdf->SetKeywords('CV, resume, curriculum vitae');
            $mpdf->SetHTMLHeader('<div style="text-align:right;font-size:8pt;color:#888;border-bottom:1px solid #ddd;padding-bottom:3px;">'.htmlspecialchars($pdfTitle).'</div>');
            $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt;color:#888;border-top:1px solid #ddd;padding-top:3px;">Page {PAGENO} of {nbpg}</div>');
            $html=mpdf_optimize_html($html);$mpdf->WriteHTML($html);
            $dest=in_array(strtolower(trim($_GET['output']??'')),['inline','preview','i'],true)?\Mpdf\Output\Destination::INLINE:\Mpdf\Output\Destination::DOWNLOAD;
            $mpdf->Output($pdfFilename,$dest);exit;
        }catch(\Throwable$e){logError('PDF Export failed: '.$e->getMessage());http_response_code(500);echo'Failed to generate PDF: '.$e->getMessage();exit;}
    }

    public static function exportDocx(string $id): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        $cvAnalyticsModel=new CvAnalyticsModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){http_response_code(403);echo'Forbidden';exit;}
        $cv=$cvModel->getById($id);
        $sections=$cvSectionModel->getByCvId($id);
        foreach($sections as&$s)$s['items']=$cvItemModel->getBySectionId($s['id']);
        $visible=array_filter($sections,fn($s)=>$s['is_visible']);
        try{$cvAnalyticsModel->trackEvent($id,'download_docx',['source'=>'export']);}catch(Throwable$e){}
        require_once dirname(__DIR__,1).'/Helpers/DocxHelper.php';
        cvGenerateDocx($cv,$visible,$cv['title'].'.docx');
    }

    public static function share(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvShareModel=new CvShareModel($mysqli);
        $cvAnalyticsModel=new CvAnalyticsModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $ex=$cvShareModel->getByCvId($id);
        if($ex){
            try{$cvAnalyticsModel->trackEvent($id,'share',['source'=>'share_link','existing'=>true]);}catch(Throwable$e){}
            jsonResponse(['success'=>true,'token'=>$ex['token'],'url'=>getAppUrl().'/cv-builder/view/'.$ex['token']]);
        }
        $token=$cvShareModel->create($id);
        if($token){
            logActivity("CV Shared","cv",$id,[],'success');
            try{$cvAnalyticsModel->trackEvent($id,'share',['source'=>'share_link','existing'=>false]);}catch(Throwable$e){}
            jsonResponse(['success'=>true,'token'=>$token,'url'=>getAppUrl().'/cv-builder/view/'.$token]);
        }else{jsonResponse(['error'=>'Failed to create share token'],500);}
    }

    public static function revokeShare(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvShareModel=new CvShareModel($mysqli);
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        jsonResponse($cvShareModel->deleteByCvId($id)?['success'=>true]:['error'=>'Failed to revoke share']);
    }

    public static function publicView(string $token): void
    {
        global $twig, $mysqli;
        $cvShareModel=new CvShareModel($mysqli);
        $share=$cvShareModel->getByToken($token);
        if(!$share){http_response_code(404);echo$twig->render('error.twig',['code'=>404,'message'=>'CV not found or expired']);exit;}
        $cvId=(int)$share['cv_id'];
        $cvModel=new CvModel($mysqli);
        $cv=$cvModel->getById($cvId);
        if(!$cv){http_response_code(404);echo$twig->render('error.twig',['code'=>404,'message'=>'CV not found']);exit;}
        $cvAnalyticsModel=new CvAnalyticsModel($mysqli);
        $cvAnalyticsModel->trackEvent($cvId,'view',['source'=>'shared_link']);
        $cvSectionModel=new CvSectionModel($mysqli);
        $cvItemModel=new CvItemModel($mysqli);
        $sections=$cvSectionModel->getByCvId($cvId);
        foreach($sections as&$s)$s['items']=$cvItemModel->getBySectionId($s['id']);
        $visible=array_filter($sections,fn($s)=>$s['is_visible']);
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($_GET['template']??null,$cv['template']??null,$t,'modern');
        echo$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible,'is_public'=>true]);
    }
}
