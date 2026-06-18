<?php

/**
 * app/Controllers/CvController.php
 * 
 * CV Controller — core page routes, CRUD, version history, analytics.
 * Builder/Section/Item logic moved to CvBuilderController.
 * Preview/Export/Sharing moved to CvExportController.
 * AI/Bulk moved to CvAiController.
 * Purchases/bKash/V3/Photo moved to CvPurchaseController.
 */

class CvController
{
    // ════════════════════════════════════════════════════════════
    // CORE PAGE ROUTES
    // ════════════════════════════════════════════════════════════

    /** GET /cv-builder/templates — Template marketplace */
    public static function marketplace(): void
    {
        global $twig, $mysqli;

        $templates = [
            'modern' => ['name' => 'Modern Professional', 'category' => 'Modern', 'description' => 'Clean design with bold purple accents, gradient headers, and skill tags.', 'gradient' => 'linear-gradient(135deg, #4F46E5, #7C3AED)', 'icon' => 'palette', 'features' => ['ATS-friendly', 'Photo-ready', 'Two-column layout', 'Color accents'], 'best_for' => 'Creative & Tech Professionals', 'popularity' => 95, 'version' => '2.2.0'],
            'minimal' => ['name' => 'Minimal Elegant', 'category' => 'Minimal', 'description' => 'Simple, elegant typography with clean lines and generous whitespace.', 'gradient' => 'linear-gradient(135deg, #374151, #6B7280)', 'icon' => 'minus', 'features' => ['Print-optimized', 'Classic layout', 'Letter-spacing', 'Simple design'], 'best_for' => 'Traditional Industries', 'popularity' => 88, 'version' => '2.1.0'],
            'ats' => ['name' => 'ATS Optimized', 'category' => 'ATS Friendly', 'description' => 'Designed specifically for Applicant Tracking Systems.', 'gradient' => 'linear-gradient(135deg, #059669, #10B981)', 'icon' => 'bot', 'features' => ['100% ATS-pass rate', 'No graphics', 'Semantic HTML', 'Keyword-optimized'], 'best_for' => 'Job Boards & ATS', 'popularity' => 92, 'version' => '3.1.0'],
            'professional' => ['name' => 'Classic Professional', 'category' => 'Professional', 'description' => 'Traditional business layout with blue tones, formal structure.', 'gradient' => 'linear-gradient(135deg, #1E40AF, #3B82F6)', 'icon' => 'briefcase', 'features' => ['Business-formal', 'Roboto font', 'Section-based', 'Executive-ready'], 'best_for' => 'Corporate & Executive', 'popularity' => 85, 'version' => '1.6.0'],
            'executive' => ['name' => 'Executive Elite', 'category' => 'Premium', 'description' => 'Gold-accented luxury design with dark header, serif typography, and two-column layout. Premium template for senior professionals.', 'gradient' => 'linear-gradient(135deg, #1A1A2E, #16213E)', 'icon' => 'crown', 'features' => ['Premium Design', 'Gold accents', 'Serif typography', 'Two-column layout', 'Dark header', 'ATS-friendly'], 'best_for' => 'Senior Executives & Leaders', 'popularity' => 98, 'version' => '1.0.0', 'is_premium' => true, 'price' => 50],
        ];

        $jobPositions = [];
        try {
            $jobPositionModel = new JobPositionModel($mysqli);
            $jobPositions = $jobPositionModel->getActivePositions();
        } catch (Throwable $e) {}

        $categories = [];
        foreach ($templates as $slug => $tmpl) {
            $cat = $tmpl['category'] ?? 'Other';
            if (!in_array($cat, $categories)) $categories[] = $cat;
        }
        sort($categories);

        $sortedKeys = array_keys($templates);
        usort($sortedKeys, function ($a, $b) use ($templates) {
            return strcmp($templates[$a]['name'], $templates[$b]['name']);
        });
        $sortedTemplates = [];
        foreach ($sortedKeys as $k) {
            $sortedTemplates[$k] = $templates[$k];
        }

        $featured = [];
        $fc = 0;
        foreach ($sortedTemplates as $slug => $tmpl) {
            if ($fc >= 2) break;
            if (($tmpl['popularity'] ?? 0) >= 90) { $featured[] = $slug; $fc++; }
        }
        if (empty($featured)) $featured = array_slice(array_keys($sortedTemplates), 0, 2);

        $categoryCounts = [];
        foreach ($sortedTemplates as $slug => $tmpl) {
            $cat = $tmpl['category'] ?? 'Other';
            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
        }

        echo $twig->render('cv/marketplace.twig', [
            'templates' => $sortedTemplates, 'featured_templates' => $featured, 'job_positions' => $jobPositions,
            'categories' => $categories, 'category_counts' => $categoryCounts, 'is_authenticated' => (getCurrentUserId() !== null),
            'page_title' => 'CV Template Marketplace',
            'breadcrumbs' => [['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'], ['label' => 'Templates', 'icon' => 'palette']]
        ]);
    }

    /** GET /cv-builder — CV Dashboard (authenticated) */
    public static function dashboard(): void
    {
        global $twig, $mysqli;
        $userId = requireAuth();
        $cvModel = new CvModel($mysqli);
        $cvs = $cvModel->getByUserId($userId);

        $userModel = new UserModel($mysqli);
        $user = $userModel->getUserById($userId);
        if ($user) {
            $user['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $user['profile_photo'] = $user['profile_pic'] ?? null;
            $palette = ['from-blue-400 to-purple-500','from-emerald-400 to-teal-500','from-orange-400 to-rose-500','from-cyan-400 to-blue-500','from-pink-400 to-fuchsia-500','from-amber-400 to-orange-500','from-violet-400 to-indigo-500','from-green-400 to-emerald-500','from-red-400 to-pink-500','from-indigo-400 to-violet-500','from-teal-400 to-cyan-500','from-rose-400 to-red-500','from-sky-400 to-indigo-500','from-lime-400 to-green-500','from-purple-400 to-fuchsia-500','from-yellow-400 to-amber-500'];
            $h = 0;
            for ($i = 0; $i < strlen($user['name'] ?? ''); $i++) $h += ord($user['name'][$i]);
            $user['avatar_color'] = $palette[$h % count($palette)];
        }

        $stats = ['total_cvs' => count($cvs), 'total_downloads' => 0, 'total_views' => 0];
        foreach ($cvs as $cv) {
            $stats['total_downloads'] += (int)($cv['download_count'] ?? 0);
            $stats['total_views'] += (int)($cv['view_count'] ?? 0);
        }

        $fid = !empty($cvs) ? $cvs[0]['id'] : null;
        $features = [
            ['icon'=>'edit','title'=>'CV Edit','description'=>"\u0986\u09aa\u09a8\u09be\u09b0 \u09b8\u09bf\u09ad\u09bf\u09a4\u09c7 \u09aa\u09b0\u09bf\u09ac\u09b0\u09cd\u09a4\u09a8 \u0995\u09b0\u09c1\u09a8 \u0993 \u0986\u09aa\u09a1\u09c7\u099f \u0995\u09b0\u09c1\u09a8",'color'=>'teal-500','action_url'=>$fid?'/cv-builder/builder/'.$fid:'/cv-builder/new','action_text'=>'Open'],
            ['icon'=>'download','title'=>'CV Download','description'=>"\u0986\u09aa\u09a8\u09be\u09b0 \u09b8\u09bf\u09ad\u09bf \u09aa\u09bf\u09a1\u09bf\u098f\u09ab \u09b9\u09bf\u09b8\u09be\u09ac\u09c7 \u09a1\u09be\u0989\u09a8\u09b2\u09cb\u09a1 \u0995\u09b0\u09c1\u09a8",'color'=>'green-500','action_url'=>$fid?'/cv-builder/'.$fid.'/export/pdf':'#','action_text'=>'Download'],
            ['icon'=>'lightbulb','title'=>'Career Advice','description'=>"AI \u09a6\u09cd\u09ac\u09be\u09b0\u09be \u09b8\u09c1\u09aa\u09be\u09b0\u09bf\u09b6\u09bf\u09a4 \u0995\u09cd\u09af\u09be\u09b0\u09bf\u09af\u09bc\u09be\u09b0 \u09aa\u09b0\u09be\u09ae\u09b0\u09cd\u09b6 \u09aa\u09be\u09a8",'color'=>'blue-500','action_url'=>'/cv-builder/advice','action_text'=>'Learn'],
            ['icon'=>'palette','title'=>'Templates','description'=>"\u09aa\u09c7\u09b6\u09be\u09a6\u09be\u09b0 \u099f\u09c7\u09ae\u09aa\u09cd\u09b2\u09c7\u099f \u09ac\u09cd\u09b0\u09be\u0989\u099c \u0995\u09b0\u09c1\u09a8 \u0993 \u09ac\u09c7\u099b\u09c7 \u09a8\u09bf\u09a8",'color'=>'orange-500','action_url'=>'/cv-builder/templates','action_text'=>'Browse'],
            ['icon'=>'briefcase','title'=>'Job Search','description'=>"\u0986\u09aa\u09a8\u09be\u09b0 \u09a6\u0995\u09cd\u09b7\u09a4\u09be\u09b0 \u09b8\u09be\u09a5\u09c7 \u09ae\u09bf\u09b2\u09c7 \u098f\u09ae\u09a8 \u099a\u09be\u0995\u09b0\u09bf \u0996\u09c1\u0981\u099c\u09c1\u09a8",'color'=>'indigo-500','action_url'=>'/jobs','action_text'=>'Search'],
            ['icon'=>'phone','title'=>'Call Expert','description'=>"\u09ac\u09bf\u09b6\u09c7\u09b7\u099c\u09cd\u099e\u09a6\u09c7\u09b0 \u09b8\u09be\u09a5\u09c7 \u09aa\u09c7\u09b6\u09be\u09a6\u09be\u09b0 \u09aa\u09b0\u09be\u09ae\u09b0\u09cd\u09b6 \u09a8\u09bf\u09a8",'color'=>'purple-500','action_url'=>'/experts','action_text'=>'Contact'],
            ['icon'=>'share-2','title'=>'CV Share','description'=>"\u0986\u09aa\u09a8\u09be\u09b0 \u09b8\u09bf\u09ad\u09bf \u09b6\u09c7\u09af\u09bc\u09be\u09b0 \u0995\u09b0\u09c1\u09a8 \u09b2\u09bf\u0999\u09cd\u0995\u09c7\u09b0 \u09ae\u09be\u09a7\u09cd\u09af\u09ae\u09c7",'color'=>'cyan-500','action_url'=>'#','action_text'=>'Share'],
            ['icon'=>'trending-up','title'=>'Analytics','description'=>"\u09a6\u09c7\u0996\u09c1\u09a8 \u0986\u09aa\u09a8\u09be\u09b0 \u09b8\u09bf\u09ad\u09bf \u0995\u09a4 \u09a6\u09c7\u0996\u09be \u09b9\u09af\u09bc\u09c7\u099b\u09c7 \u0993 \u09a1\u09be\u0989\u09a8\u09b2\u09cb\u09a1 \u09b9\u09af\u09bc\u09c7\u099b\u09c7",'color'=>'rose-500','action_url'=>'/cv-builder/analytics','action_text'=>'View'],
        ];

        $ps = null;
        if (!empty($cvs)) {
            foreach ($cvs as $cv) { if (!empty($cv['is_active']) && !empty($cv['professional_status'])) { $ps = $cv['professional_status']; break; } }
            if ($ps === null && !empty($cvs[0]['professional_status'])) $ps = $cvs[0]['professional_status'];
        }

        echo $twig->render('cv/dashboard.twig', [
            'user' => $user, 'cvs' => $cvs, 'stats' => $stats, 'features' => $features,
            'active_cv_professional_status' => $ps, 'page_title' => 'My CVs',
            'breadcrumbs' => [['label' => 'CV Builder', 'icon' => 'file-earmark-text']]
        ]);
    }

    /** GET /cv-builder/new — Create new CV page */
    public static function createForm(): void
    {
        global $twig;
        echo $twig->render('cv/new.twig', [
            'page_title' => 'Create CV',
            'breadcrumbs' => [['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'], ['label' => 'Create New', 'icon' => 'plus-circle']]
        ]);
    }

    /** GET /cv-builder/builder/{id} — Builder wizard page */
    public static function builder(string $id): void
    {
        global $twig, $mysqli;
        $userId = requireAuth();
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, $userId)) {
            http_response_code(403);
            echo $twig->render('error.twig', ['code' => 403, 'message' => 'Forbidden']);
            exit;
        }
        $cv = $cvModel->getById($id);
        $bd = $cvModel->getBuilderData($id);
        $jobPositions = [];
        try {
            $jobPositionModel = new JobPositionModel($mysqli);
            $jobPositions = $jobPositionModel->getActivePositions();
        } catch (Throwable $e) {}
        $t = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        echo $twig->render('cv/form.twig', [
            'cv' => $cv, 'cv_id' => $id, 'builder_data' => $bd, 'job_positions' => $jobPositions,
            'templates' => $t, 'selected_template' => $cv['template'] ?? 'modern',
            'page_title' => 'Build Your CV',
            'breadcrumbs' => [['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'], ['label' => 'Build CV', 'icon' => 'pencil-square']]
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // CRUD
    // ════════════════════════════════════════════════════════════

    /** POST /cv-builder — Create a new CV */
    public static function store(): void
    {
        global $mysqli;
        $userId = requireAuth();
        $cvModel = new CvModel($mysqli);
        $cvSectionModel = new CvSectionModel($mysqli);
        $title = sanitize_input($_POST['title'] ?? 'My CV');
        $profession = sanitize_input($_POST['profession'] ?? '');
        $template = sanitize_input($_POST['template'] ?? 'modern');
        $ps = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;
        $cvId = $cvModel->create($userId, $title, 'modern', $ps);
        if ($cvId) {
            foreach (cvDefaultSectionTypes() as $t => $st) $cvSectionModel->create($cvId, $t, $st);
            if (!empty($profession)) {
                try {
                    $jpm = new JobPositionModel($mysqli);
                    $pos = $jpm->getPositionBySlug($profession);
                    if ($pos && $pos['is_active']) {
                        foreach ($jpm->getSummaries((int)$pos['id']) as $sum) {
                            foreach ($cvSectionModel->getByCvId($cvId) as $sec) {
                                if ($sec['section_type'] === 'summary') {
                                    (new CvItemModel($mysqli))->create($sec['id'], 'text', ['content'=>$sum['content'],'type'=>$sum['type']]);
                                    break;
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $sums = function_exists('cvTemplateGetProfessionSummaries') ? cvTemplateGetProfessionSummaries() : [];
                    if (isset($sums[$profession])) {
                        foreach ($cvSectionModel->getByCvId($cvId) as $sec) {
                            if ($sec['section_type'] === 'summary') {
                                (new CvItemModel($mysqli))->create($sec['id'], 'text', ['content'=>$sums[$profession]]);
                                break;
                            }
                        }
                    }
                }
            }
            logActivity("CV Created", "cv", $cvId, ['title'=>$title,'profession'=>$profession,'template'=>$template], 'success');
            showMessage("CV created successfully", "success");
            header('Location: /cv-builder/'.$cvId);
        } else {
            showMessage("Failed to create CV", "danger");
            header('Location: /cv-builder');
        }
        exit;
    }

    /** GET /cv-builder/form-data — Get form data for current user */
    public static function formData(): void
    {
        global $mysqli;
        $userId = requireAuth();
        $cvModel = new CvModel($mysqli);
        $cvs = $cvModel->getByUserId($userId);
        if (!empty($cvs)) {
            $cv = $cvs[0];
            $bd = $cvModel->getBuilderData($cv['id']);
            jsonResponse(['success'=>true,'data'=>['cv_id'=>$cv['id'],'full_name'=>$bd['personal']['full_name']??'','professional_summary'=>$bd['summary']['professional_summary']??'','email'=>$bd['personal']['email']??'','phone'=>$bd['personal']['phone']??'','location'=>$bd['personal']['location']??'','professional_status'=>$cv['professional_status']??'','title'=>$cv['title']??'My CV'],'has_cv'=>true,'total_cvs'=>count($cvs)]);
        } else {
            jsonResponse(['success'=>true,'data'=>['cv_id'=>null,'full_name'=>'','professional_summary'=>'','email'=>'','phone'=>'','location'=>'','professional_status'=>'','title'=>'My CV'],'has_cv'=>false,'total_cvs'=>0]);
        }
    }

    /** POST /cv-builder/save — Save CV from dashboard form */
    public static function save(): void
    {
        global $mysqli;
        $userId = requireAuth();
        $cvModel = new CvModel($mysqli);
        $cvSectionModel = new CvSectionModel($mysqli);
        $cvItemModel = new CvItemModel($mysqli);
        $fn = sanitize_input($_POST['full_name']??'');
        $ps = sanitize_input($_POST['professional_summary']??'');
        $em = sanitize_input($_POST['email']??'');
        $ph = sanitize_input($_POST['phone']??'');
        $loc = sanitize_input($_POST['location']??'');
        $profStatus = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;
        $cvId = !empty($_POST['cv_id']) ? (int)$_POST['cv_id'] : null;
        if (empty($fn)) { jsonResponse(['success'=>false,'message'=>"\u09aa\u09c2\u09b0\u09cd\u09a3 \u09a8\u09be\u09ae \u09aa\u09cd\u09b0\u09af\u09bc\u09cb\u099c\u09a8"],400); return; }
        try {
            if ($cvId) {
                if (!$cvModel->belongsToUser($cvId,$userId)) { jsonResponse(['success'=>false,'message'=>"\u0985\u09a8\u09c1\u09ae\u09a4\u09bf \u09a8\u09c7\u0987"],403); return; }
                $ex = $cvModel->getBuilderData($cvId);
                $bd = ['personal'=>['full_name'=>$fn,'email'=>$em,'phone'=>$ph,'location'=>$loc],'summary'=>['professional_summary'=>$ps]];
                foreach ($ex as $k=>$v) { if (!isset($bd[$k])) $bd[$k] = $v; }
                if ($cvModel->update($cvId,['professional_status'=>$profStatus,'builder_data'=>$bd])) {
                    logActivity("CV Updated from Dashboard","cv",$cvId,['full_name'=>$fn],'success');
                    jsonResponse(['success'=>true,'message'=>"\u09b8\u09bf\u09ad\u09bf \u09b8\u09ab\u09b2\u09ad\u09be\u09ac\u09c7 \u0986\u09aa\u09a1\u09c7\u099f \u09b9\u09af\u09bc\u09c7\u099b\u09c7",'cv_id'=>$cvId]);
                } else { jsonResponse(['success'=>false,'message'=>"\u0986\u09aa\u09a1\u09c7\u099f \u09ac\u09cd\u09af\u09b0\u09cd\u09a5 \u09b9\u09af\u09bc\u09c7\u099b\u09c7"],500); }
            } else {
                $newId = $cvModel->create($userId,$fn."'s CV",'modern',$profStatus);
                if ($newId) {
                    foreach (cvDefaultSectionTypes() as $t=>$st) $cvSectionModel->create($newId,$t,$st);
                    $cvModel->update($newId,['builder_data'=>['personal'=>['full_name'=>$fn,'email'=>$em,'phone'=>$ph,'location'=>$loc],'summary'=>['professional_summary'=>$ps]]]);
                    logActivity("CV Created from Dashboard","cv",$newId,['full_name'=>$fn],'success');
                    jsonResponse(['success'=>true,'message'=>"\u09b8\u09bf\u09ad\u09bf \u09b8\u09ab\u09b2\u09ad\u09be\u09ac\u09c7 \u09a4\u09c8\u09b0\u09bf \u09b9\u09af\u09bc\u09c7\u099b\u09c7",'cv_id'=>$newId]);
                } else { jsonResponse(['success'=>false,'message'=>"\u09b8\u09bf\u09ad\u09bf \u09a4\u09c8\u09b0\u09bf \u09ac\u09cd\u09af\u09b0\u09cd\u09a5 \u09b9\u09af\u09bc\u09c7\u099b\u09c7"],500); }
            }
        } catch (Throwable $e) {
            logError('CV Save Error: '.$e->getMessage(),'error',['user_id'=>$userId]);
            jsonResponse(['success'=>false,'message'=>"\u098f\u0995\u099f\u09bf \u09a4\u09cd\u09b0\u09c1\u099f\u09bf \u0998\u099f\u09c7\u099b\u09c7"],500);
        }
    }

    /** GET /cv-builder/download — Redirect to first CV pdf export */
    public static function redirectDownload(): void
    {
        global $mysqli;
        $userId = requireAuth();
        $cvModel = new CvModel($mysqli);
        $cvs = $cvModel->getByUserId($userId);
        header('Location: '.(!empty($cvs)?'/cv-builder/'.(int)$cvs[0]['id'].'/export/pdf':'/cv-builder/new'));
        exit;
    }

    /** GET /cv-builder/share — Redirect to first CV */
    public static function redirectShare(): void
    {
        global $mysqli;
        $userId = requireAuth();
        $cvModel = new CvModel($mysqli);
        $cvs = $cvModel->getByUserId($userId);
        header('Location: '.(!empty($cvs)?'/cv-builder/'.(int)$cvs[0]['id']:'/cv-builder/new'));
        exit;
    }

    /** GET /cv-builder/view — Redirect to first CV */
    public static function redirectView(): void
    {
        global $mysqli;
        $userId = requireAuth();
        $cvModel = new CvModel($mysqli);
        $cvs = $cvModel->getByUserId($userId);
        header('Location: '.(!empty($cvs)?'/cv-builder/'.(int)$cvs[0]['id']:'/cv-builder/new'));
        exit;
    }

    /** GET /api/cv/templates/{slug}/preview — Render template preview with sample data */
    public static function templatePreview(string $slug): void
    {
        global $mysqli, $twig;
        $slug = basename($slug); // prevent path traversal
        try {
            $previewService = new CvPreviewService($mysqli, $twig);
            $result = $previewService->renderTemplatePreview($slug);
            if (!$result['success']) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => $result['error'] ?? 'Template not found']);
                return;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo $result['html'];
        } catch (Throwable $e) {
            logError('Template preview error: ' . $e->getMessage(), 'ERROR', ['slug' => $slug]);
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;color:#6b7280;"><p>Failed to load template preview.</p></body></html>';
        }
    }

    /** GET /cv-builder/{id} — Redirect to builder wizard */
    public static function redirectToBuilder(string $id): void
    {
        global $twig, $mysqli;
        $userId = requireAuth();
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, $userId)) {
            http_response_code(403);
            echo $twig->render('error.twig', ['code'=>403, 'message'=>'Forbidden']);
            exit;
        }
        header('Location: /cv-builder/builder/'.$id);
        exit;
    }

    /** PUT /cv-builder/{id} — Update CV (API) */
    public static function update(string $id): void
    {
        global $mysqli;
        $userId = requireAuth();
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, $userId)) { jsonResponse(['error'=>'Forbidden'],403); return; }
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        if (isset($data['title'])) $data['title'] = sanitize_input((string)$data['title']);
        if (isset($data['template'])) $data['template'] = cvResolveTemplate((string)$data['template'], null, cvGetTemplateAllowlist(), 'modern');
        if (isset($data['content']) && is_array($data['content'])) {
            $ex = $cvModel->getBuilderData($id);
            foreach ($data['content'] as $k=>$v) $ex[$k] = is_string($v) ? sanitize_input($v) : $v;
            $data['builder_data'] = $ex; unset($data['content']);
        }
        jsonResponse($cvModel->update($id,$data) ? ['success'=>true,'message'=>'CV updated'] : ['error'=>'Failed to update CV']);
    }

    /** POST /cv-builder/{id}/update — Update CV (form) */
    public static function updateForm(string $id): void
    {
        global $twig, $mysqli;
        $userId = requireAuth();
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id,$userId)) { http_response_code(403); echo 'Forbidden'; exit; }
        $ud = ['title'=>!empty($_POST['title'])?sanitize_input($_POST['title']):'My CV','template'=>sanitize_input($_POST['template']??'modern'),'professional_status'=>!empty($_POST['professional_status'])?sanitize_input($_POST['professional_status']):null,'builder_data'=>['summary'=>sanitize_input($_POST['summary']??''),'skills'=>sanitize_input($_POST['skills']??''),'experience'=>sanitize_input($_POST['experience']??''),'education'=>sanitize_input($_POST['education']??'')]];
        $ok = $cvModel->update($id,$ud);
        showMessage($ok ? 'CV updated successfully' : 'Failed to update CV', $ok ? 'success' : 'danger');
        header('Location: /cv-builder/'.$id);
        exit;
    }

    /** POST /cv-builder/{id}/duplicate — Duplicate a CV */
    public static function duplicate(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel = new CvModel($mysqli);
        $cvSectionModel = new CvSectionModel($mysqli);
        $cvItemModel = new CvItemModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $o=$cvModel->getById($id);if(!$o){jsonResponse(['error'=>'CV not found'],404);return;}
        $nid=$cvModel->create($userId,($o['title']??'My CV').' (Copy)',$o['template']??'modern',$o['professional_status']??null);
        if(!$nid){jsonResponse(['error'=>'Failed to create duplicate'],500);return;}
        if(!empty($o['builder_data']))$cvModel->update($nid,['builder_data'=>$o['builder_data']]);
        foreach($cvSectionModel->getByCvId($id)as$s){$ns=$cvSectionModel->create($nid,$s['section_type'],$s['title']);if($ns)foreach($cvItemModel->getBySectionId($s['id'])as$i)$cvItemModel->create($ns,$i['item_type']??'generic',$i['content']??[]);}
        logActivity("CV Duplicated","cv",$nid,['original_id'=>$id],'success');
        jsonResponse(['success'=>true,'message'=>'CV duplicated successfully','new_cv_id'=>$nid,'redirect'=>'/cv-builder/'.$nid]);
    }

    /** DELETE /cv-builder/{id} — Soft-delete a CV */
    public static function delete(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel = new CvModel($mysqli);
        $cvShareModel = new CvShareModel($mysqli);
        $cvVersionModel = new CvVersionModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        try{$cvVersionModel->createVersion($id,$userId);$cvVersionModel->pruneVersions($id,10);}catch(Throwable$e){}
        $cvShareModel->deleteByCvId($id);
        jsonResponse($cvModel->delete($id)?['success'=>true,'message'=>'CV deleted']:['error'=>'Failed to delete CV']);
    }

    // ════════════════════════════════════════════════════════════
    // VERSION HISTORY
    // ════════════════════════════════════════════════════════════

    /** GET /cv-builder/{id}/versions — List versions */
    public static function listVersions(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvVersionModel=new CvVersionModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $limit=(int)($_GET['limit']??20);$offset=(int)($_GET['offset']??0);
        jsonResponse(['success'=>true,'versions'=>$cvVersionModel->getVersions($id,$limit,$offset)]);
    }

    /** GET /cv-builder/{id}/versions/{version} — Get specific version */
    public static function getVersion(string $id, string $version): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $version=(int)$version;
        $cvModel=new CvModel($mysqli);
        $cvVersionModel=new CvVersionModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $vd=$cvVersionModel->getVersion($id,$version);
        jsonResponse($vd?['success'=>true,'version'=>$vd]:['error'=>'Version not found'],$vd?200:404);
    }

    /** POST /cv-builder/{id}/versions/{version}/restore — Restore version */
    public static function restoreVersion(string $id, string $version): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $version=(int)$version;
        $cvModel=new CvModel($mysqli);
        $cvVersionModel=new CvVersionModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        jsonResponse($cvVersionModel->restoreVersion($id,$version,$userId)?['success'=>true,'message'=>'Version restored']:['error'=>'Failed to restore version']);
    }

    /** GET /cv-builder/{id}/versions/compare/{v1}/{v2} — Compare versions */
    public static function compareVersions(string $id, string $v1, string $v2): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $v1=(int)$v1;
        $v2=(int)$v2;
        $cvModel=new CvModel($mysqli);
        $cvVersionModel=new CvVersionModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        jsonResponse(['success'=>true,'diff'=>$cvVersionModel->compareVersions($id,$v1,$v2)]);
    }

    // ════════════════════════════════════════════════════════════
    // ANALYTICS & RATE LIMITS
    // ════════════════════════════════════════════════════════════

    /** GET /cv-builder/{id}/analytics — CV analytics */
    public static function cvAnalytics(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvAnalyticsModel=new CvAnalyticsModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        jsonResponse(['success'=>true,'analytics'=>$cvAnalyticsModel->getCvAnalytics($id,$_GET['period']??'month')]);
    }

    /** GET /cv-builder/analytics/summary — Analytics summary */
    public static function analyticsSummary(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvAnalyticsModel=new CvAnalyticsModel($mysqli);
        jsonResponse(['success'=>true,'summary'=>$cvAnalyticsModel->getUserSummary($userId)]);
    }

    /** GET /cv-builder/rate-limits — User rate limits */
    public static function rateLimits(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvRateLimitModel=new CvRateLimitModel($mysqli);
        jsonResponse(['success'=>true,'rate_limits'=>$cvRateLimitModel->getUserRateLimits($userId)]);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS (kept for backward compatibility — used by templates, etc.)
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('requireAuth')) {
    function requireAuth(): int
    {
        $userId = getCurrentUserId();
        if (!$userId) {
            json_response(['error' => 'Unauthorized'], 401);
        }
        return $userId;
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): void
    {
        json_response($data, $statusCode);
    }
}

if (!function_exists('cvGetTemplateAllowlist')) {
    function cvGetTemplateAllowlist(): array
    {
        $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
        $files = glob($dir . '/*.twig') ?: [];
        $templates = [];
        foreach ($files as $file) {
            $name = basename($file, '.twig');
            if ($name === '' || $name[0] === '_') continue;
            if (function_exists('cvTemplateIsDisabled') && cvTemplateIsDisabled($name)) continue;
            $templates[] = $name;
        }
        $templates = array_values(array_unique($templates));
        sort($templates);
        return $templates;
    }
}

if (!function_exists('cvResolveTemplate')) {
    function cvResolveTemplate(?string $requested, ?string $cvTemplate, array $allowlist, string $default = 'modern'): string
    {
        $requested = is_string($requested) ? trim($requested) : '';
        $cvTemplate = is_string($cvTemplate) ? trim($cvTemplate) : '';
        if ($requested !== '' && in_array($requested, $allowlist, true)) return $requested;
        if ($cvTemplate !== '' && in_array($cvTemplate, $allowlist, true)) return $cvTemplate;
        return in_array($default, $allowlist, true) ? $default : ($allowlist[0] ?? $default);
    }
}

if (!function_exists('cvDefaultSectionTypes')) {
    function cvDefaultSectionTypes(): array
    {
        return [
            'summary' => 'Professional Summary',
            'experience' => 'Work Experience',
            'education' => 'Education',
            'skills' => 'Skills',
        ];
    }
}

if (!function_exists('cvRenderA4PreviewHtml')) {
    function cvRenderA4PreviewHtml(string $innerHtml, string $templateSlug, int $cvId, float $zoom = 1.0, int $completionScore = 0): string
    {
        $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        $templateOptions = '';
        foreach ($templates as $t) {
            $sel = $t === $templateSlug ? ' selected' : '';
            $label = ucfirst($t);
            $templateOptions .= "<option value=\"{$t}\"{$sel}>{$label}</option>";
        }
        $scoreClass = 'poor';
        if ($completionScore >= 70) $scoreClass = 'good';
        elseif ($completionScore >= 40) $scoreClass = 'warn';

        return <<<A4HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CV Preview — {$templateSlug}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #e5e7eb; display: flex; flex-direction: column; align-items: center; padding: 40px 20px; font-family: system-ui, -apple-system, sans-serif; min-height: 100vh; }
  .preview-toolbar { position: sticky; top: 0; z-index: 100; background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 12px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: center; width: 100%; max-width: 900px; }
  .preview-toolbar button, .preview-toolbar select { padding: 6px 14px; border: 1px solid #d1d5db; border-radius: 6px; background: white; font-size: 13px; cursor: pointer; transition: all 0.15s; }
  .preview-toolbar button:hover { background: #f3f4f6; border-color: #9ca3af; }
  .preview-toolbar .zoom-label { font-size: 13px; color: #6b7280; font-weight: 500; }
  .preview-toolbar .score-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
  .score-badge.good { background: #d1fae5; color: #065f46; }
  .score-badge.warn { background: #fef3c7; color: #92400e; }
  .score-badge.poor { background: #fee2e2; color: #991b1b; }
  .a4-page { width: 210mm; min-height: 297mm; background: white; box-shadow: 0 4px 24px rgba(0,0,0,0.12); transform-origin: top center; transition: transform 0.2s ease; }
  @media print { body { background: white; padding: 0; } .preview-toolbar { display: none !important; } .a4-page { box-shadow: none; } }
  @media (max-width: 800px) { body { padding: 10px; } .a4-page { width: 100%; min-height: auto; transform: none !important; padding: 0; } }
</style>
</head>
<body>
<div class="preview-toolbar">
  <span class="zoom-label">Template:</span>
  <select id="template-select" onchange="window.parent.postMessage({type:'template-change', template:this.value}, '*')">{$templateOptions}</select>
  <span style="color:#d1d5db;">|</span>
  <span class="zoom-label">Zoom:</span>
  <button onclick="zoomIn()" title="Zoom In">+</button>
  <span id="zoom-level" style="font-size:13px;min-width:48px;text-align:center;">{$zoom}x</span>
  <button onclick="zoomOut()" title="Zoom Out">-</button>
  <button onclick="zoomReset()" title="Reset Zoom">Reset</button>
  <span style="color:#d1d5db;">|</span>
  <button onclick="window.print()" title="Print Preview">Print</button>
  <span class="score-badge {$scoreClass}">Completion: {$completionScore}%</span>
</div>
<div class="a4-page" id="cv-preview-content" style="transform: scale({$zoom});">{$innerHtml}</div>
</body>
</html>
A4HTML;
    }
}

if (!function_exists('cvMergeContent')) {
    function cvMergeContent(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if ($value === null) { unset($base[$key]); continue; }
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = cvMergeContent($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }
}
