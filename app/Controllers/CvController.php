<?php

/**
 * app/Controllers/CvController.php
 * 
 * CV Controller — consolidated controller for all user/guest CV features.
 * Includes: core CRUD, builder wizard, AI features, export/preview,
 * premium purchases, payment callbacks, photo uploads, and favorites.
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
            'creative' => ['name' => 'Creative Portfolio', 'category' => 'Creative', 'description' => 'Bold pink-orange gradient design with vibrant skill tags, colorful badges, and card-based layout. Perfect for showcasing a creative portfolio.', 'gradient' => 'linear-gradient(135deg, #EC4899, #F97316)', 'icon' => 'palette', 'features' => ['Vibrant colors', 'Colorful skill tags', 'Card-based layout', 'Photo-ready banner', 'Two-column design', 'Print-optimized'], 'best_for' => 'Designers & Creative Professionals', 'popularity' => 90, 'version' => '1.0.0'],
            'classic' => ['name' => 'Classic Traditional', 'category' => 'Classic', 'description' => 'Timeless serif typography with navy tones, elegant gold accents, and a refined single-column layout. Perfect for conservative industries like law, finance, and academia.', 'gradient' => 'linear-gradient(135deg, #1B2A4A, #2D3B5C)', 'icon' => 'book', 'features' => ['Serif typography', 'Single-column layout', 'Gold accent details', 'Print-optimized', 'ATS-compatible', 'Elegant design'], 'best_for' => 'Law, Finance & Academia', 'popularity' => 82, 'version' => '1.0.0'],
            'technical' => ['name' => 'Technical Engineer', 'category' => 'Technical', 'description' => 'Modern two-column layout with dark sidebar, monospace-styled tech stack badges, and project highlight cards. Optimized for software engineers showcasing skills and projects.', 'gradient' => 'linear-gradient(135deg, #0F172A, #0F766E)', 'icon' => 'terminal', 'features' => ['Dark sidebar', 'Tech stack badges', 'Project cards', 'Monospace accents', 'Proficiency indicators', 'ATS-friendly'], 'best_for' => 'Software Engineers & Developers', 'popularity' => 86, 'version' => '1.0.0'],
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
        ];

        $ps = null;
        if (!empty($cvs)) {
            foreach ($cvs as $cv) { if (!empty($cv['is_active']) && !empty($cv['professional_status'])) { $ps = $cv['professional_status']; break; } }
            if ($ps === null && !empty($cvs[0]['professional_status'])) $ps = $cvs[0]['professional_status'];
        }

        // Check for guest CV auto-claim flash message
        $guestCvsClaimed = 0;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['guest_cvs_just_claimed'])) {
            $guestCvsClaimed = (int)$_SESSION['guest_cvs_just_claimed'];
            unset($_SESSION['guest_cvs_just_claimed']);
        }

        echo $twig->render('cv/dashboard.twig', [
            'user' => $user, 'cvs' => $cvs, 'stats' => $stats, 'features' => $features,
            'active_cv_professional_status' => $ps, 'page_title' => 'My CVs',
            'guest_cvs_claimed' => $guestCvsClaimed,
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
        $title = sanitize_input($_POST['title'] ?? 'My CV');
        $template = cvResolveTemplate(
            $_POST['template'] ?? null,
            null,
            cvGetTemplateAllowlist(),
            'modern'
        );
        $ps = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;
        $cvId = $cvModel->create($userId, $title, $template, $ps);
        if ($cvId) {
            $cvModel->update($cvId, ['template' => $template]);
            logActivity("CV Created", "cv", $cvId, ['title'=>$title,'template'=>$template], 'success');
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
        $fn = sanitize_input($_POST['full_name']??'');
        $ps = sanitize_input($_POST['professional_summary']??'');
        $em = sanitize_input($_POST['email']??'');
        $ph = sanitize_input($_POST['phone']??'');
        $loc = sanitize_input($_POST['location']??'');
        $profStatus = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;
        $template = cvResolveTemplate($_POST['template'] ?? null, null, cvGetTemplateAllowlist(), 'modern');
        $cvId = !empty($_POST['cv_id']) ? (int)$_POST['cv_id'] : null;
        if (empty($fn)) { jsonResponse(['success'=>false,'message'=>"\u09aa\u09c2\u09b0\u09cd\u09a3 \u09a8\u09be\u09ae \u09aa\u09cd\u09b0\u09af\u09bc\u09cb\u099c\u09a8"],400); return; }
        try {
            if ($cvId) {
                if (!$cvModel->belongsToUser($cvId,$userId)) { jsonResponse(['success'=>false,'message'=>"\u0985\u09a8\u09c1\u09ae\u09a4\u09bf \u09a8\u09c7\u0987"],403); return; }
                $ex = $cvModel->getBuilderData($cvId);
                $bd = ['personal'=>['full_name'=>$fn,'email'=>$em,'phone'=>$ph,'location'=>$loc],'summary'=>['professional_summary'=>$ps]];
                foreach ($ex as $k=>$v) { if (!isset($bd[$k])) $bd[$k] = $v; }
                if ($cvModel->update($cvId,['professional_status'=>$profStatus,'template'=>$template,'builder_data'=>$bd])) {
                    logActivity("CV Updated from Dashboard","cv",$cvId,['full_name'=>$fn],'success');
                    jsonResponse(['success'=>true,'message'=>"\u09b8\u09bf\u09ad\u09bf \u09b8\u09ab\u09b2\u09ad\u09be\u09ac\u09c7 \u0986\u09aa\u09a1\u09c7\u099f \u09b9\u09af\u09bc\u09c7\u099b\u09c7",'cv_id'=>$cvId]);
                } else { jsonResponse(['success'=>false,'message'=>"\u0986\u09aa\u09a1\u09c7\u099f \u09ac\u09cd\u09af\u09b0\u09cd\u09a5 \u09b9\u09af\u09bc\u09c7\u099b\u09c7"],500); }
            } else {
                $newId = $cvModel->create($userId,$fn."'s CV",$template,$profStatus);
                if ($newId) {
                    $cvModel->update($newId,['template'=>$template,'builder_data'=>['personal'=>['full_name'=>$fn,'email'=>$em,'phone'=>$ph,'location'=>$loc],'summary'=>['professional_summary'=>$ps]]]);
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
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $o=$cvModel->getById($id);if(!$o){jsonResponse(['error'=>'CV not found'],404);return;}
        $nid=$cvModel->create($userId,($o['title']??'My CV').' (Copy)',$o['template']??'modern',$o['professional_status']??null);
        if(!$nid){jsonResponse(['error'=>'Failed to create duplicate'],500);return;}
        if(!empty($o['builder_data']))$cvModel->update($nid,['builder_data'=>$o['builder_data']]);
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
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        jsonResponse($cvModel->delete($id)?['success'=>true,'message'=>'CV deleted']:['error'=>'Failed to delete CV']);
    }

    // ════════════════════════════════════════════════════════════
    // RATE LIMITS
    // ════════════════════════════════════════════════════════════

    /** GET /cv-builder/rate-limits — User rate limits */
    public static function rateLimits(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvRateLimitModel=new CvRateLimitModel($mysqli);
        jsonResponse(['success'=>true,'rate_limits'=>$cvRateLimitModel->getUserRateLimits($userId)]);
    }

    // ════════════════════════════════════════════════════════════
    // GUEST CV BUILDER (no auth required, minimal template only)
    // ════════════════════════════════════════════════════════════

    // ════════════════════════════════════════════════════════════
    // GUEST CV CLAIMING & UPGRADE
    // ════════════════════════════════════════════════════════════

    /**
     * POST /api/cv/claim-guest-cvs — Claim guest CVs for the authenticated user.
     * Returns the number of CVs claimed.
     */
    public static function claimGuestCvs(): void
    {
        global $mysqli;
        $userId = getCurrentUserId();
        if (!$userId) {
            jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        $cvModel = new CvModel($mysqli);
        $claimed = $cvModel->claimGuestCvsForUser($userId);
        if ($claimed > 0) {
            logActivity("Guest CVs Claimed", "cv", null, ['user_id' => $userId, 'count' => $claimed], 'success');
            // Set session flash so the notification shows on page reload
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['guest_cvs_just_claimed'] = $claimed;
        }
        jsonResponse([
            'success' => true,
            'claimed' => $claimed,
            'message' => $claimed > 0
                ? "{$claimed} CV(s) claimed successfully!"
                : 'No guest CVs found to claim.'
        ]);
    }

    /**
     * GET /api/cv/has-guest-cvs — Check if the current user has guest CVs to claim.
     */
    public static function hasGuestCvs(): void
    {
        global $mysqli;
        $cvModel = new CvModel($mysqli);
        $guestIds = $cvModel->getGuestCvIds();
        $hasCvs = false;
        foreach ($guestIds as $cvId) {
            $cv = $cvModel->getById($cvId);
            if ($cv && $cv['user_id'] === null) {
                $hasCvs = true;
                break;
            }
        }
        jsonResponse(['success' => true, 'has_guest_cvs' => $hasCvs, 'count' => count($guestIds)]);
    }

    /**
     * GET /api/cv/my-cvs — Get user's CVs list (id, title, template).
     */
    public static function myCvs(): void
    {
        global $mysqli;
        $userId = getCurrentUserId();
        if (!$userId) {
            jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        $cvModel = new CvModel($mysqli);
        $cvs = $cvModel->getByUserId($userId);
        $result = [];
        foreach ($cvs as $cv) {
            $result[] = [
                'id' => (int)$cv['id'],
                'title' => $cv['title'] ?? 'My CV',
                'template' => $cv['template'] ?? 'modern',
            ];
        }
        jsonResponse(['success' => true, 'cvs' => $result]);
    }

    /**
     * POST /api/cv/{id}/upgrade-template — Upgrade a CV to a purchased premium template.
     * Verifies the user has purchased the target template before upgrading.
     */
    public static function upgradeTemplate(string $id): void
    {
        global $mysqli;
        $userId = getCurrentUserId();
        if (!$userId) {
            jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        
        // Check ownership (works for both regular and claimed guest CVs)
        if (!$cvModel->belongsToUser($id, $userId)) {
            jsonResponse(['error' => 'Forbidden'], 403);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $targetTemplate = sanitize_input($input['template'] ?? '');
        if (empty($targetTemplate)) {
            jsonResponse(['error' => 'Template slug is required'], 400);
            return;
        }
        
        // Validate the template slug is valid
        $allowlist = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional', 'executive'];
        if (!in_array($targetTemplate, $allowlist, true)) {
            jsonResponse(['error' => 'Invalid template slug'], 400);
            return;
        }
        
        // Verify the user has purchased this template
        $stmt = $mysqli->prepare(
            "SELECT id FROM cv_template_purchases 
             WHERE user_id = ? AND template_slug = ? AND status = 'completed' AND deleted_at IS NULL 
             LIMIT 1"
        );
        $stmt->bind_param('is', $userId, $targetTemplate);
        $stmt->execute();
        $purchased = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$purchased) {
            jsonResponse(['error' => 'You have not purchased this template'], 403);
            return;
        }
        
        // Update the CV's template
        $updated = $cvModel->update($id, ['template' => $targetTemplate]);
        
        if ($updated) {
            logActivity("CV Template Upgraded", "cv", $id, [
                'user_id' => $userId,
                'new_template' => $targetTemplate,
            ], 'success');
            jsonResponse([
                'success' => true,
                'message' => 'CV template upgraded successfully!',
                'new_template' => $targetTemplate,
            ]);
        } else {
            jsonResponse(['error' => 'Failed to upgrade template'], 500);
        }
    }

    /** GET /cv-builder/guest — Guest CV dashboard */
    public static function guestDashboard(): void
    {
        global $twig, $mysqli;
        $cvModel = new CvModel($mysqli);
        $guestIds = $cvModel->getGuestCvIds();
        $cvs = [];
        foreach ($guestIds as $cvId) {
            $cv = $cvModel->getById($cvId);
            if ($cv && $cv['user_id'] === null) {
                $cvs[] = $cv;
            }
        }
        echo $twig->render('cv/guest-dashboard.twig', [
            'cvs' => $cvs,
            'page_title' => 'CV Builder - Guest',
            'breadcrumbs' => [['label' => 'CV Builder', 'icon' => 'file-earmark-text'], ['label' => 'Guest Mode', 'icon' => 'person-badge']]
        ]);
    }

    /** GET /cv-builder/guest/builder/{id} — Guest builder wizard (minimal template only) */
    public static function guestBuilder(string $id): void
    {
        global $twig, $mysqli;
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, null)) {
            http_response_code(403);
            echo $twig->render('error.twig', ['code' => 403, 'message' => 'Forbidden or CV not found']);
            exit;
        }
        $cv = $cvModel->getById($id);
        $bd = $cvModel->getBuilderData($id);
        $jobPositions = [];
        try {
            $jobPositionModel = new JobPositionModel($mysqli);
            $jobPositions = $jobPositionModel->getActivePositions();
        } catch (Throwable $e) {}
        echo $twig->render('cv/guest-form.twig', [
            'cv' => $cv, 'cv_id' => $id, 'builder_data' => $bd, 'job_positions' => $jobPositions,
            'selected_template' => 'minimal',
            'page_title' => 'Build Your CV (Guest Mode)',
            'breadcrumbs' => [['label' => 'CV Builder', 'url' => '/cv-builder/guest', 'icon' => 'file-earmark-text'], ['label' => 'Build CV', 'icon' => 'pencil-square']]
        ]);
    }

    /** POST /cv-builder/guest — Create a new guest CV */
    public static function guestStore(): void
    {
        global $mysqli;
        $cvModel = new CvModel($mysqli);
        $title = sanitize_input($_POST['title'] ?? 'My CV');
        $cvId = $cvModel->create(null, $title, 'minimal');
        if ($cvId) {
            showMessage("CV created successfully", "success");
            header('Location: /cv-builder/guest/builder/'.$cvId);
        } else {
            showMessage("Failed to create CV", "danger");
            header('Location: /cv-builder/guest');
        }
        exit;
    }

    /** POST /api/cv/guest/builder/{id}/step — Save builder step for guest CV */
    public static function guestSaveStep(string $id): void
    {
        $id = (int)$id;
        global $mysqli;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, null)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['step'])) { jsonResponse(['error' => 'Step name is required'], 400); return; }
        $step = sanitize_input($input['step']);
        $sd = $input['data'] ?? [];
        array_walk_recursive($sd, function (&$v) { if (is_string($v)) $v = sanitize_input($v); });
        jsonResponse($cvModel->saveBuilderStep($id, $step, $sd) ? ['success' => true, 'message' => 'Step saved'] : ['error' => 'Failed to save step']);
    }

    /** GET /api/cv/guest/builder/{id}/progress — Get builder progress */
    public static function guestBuilderProgress(string $id): void
    {
        $id = (int)$id;
        global $mysqli;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, null)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $d = $cvModel->getBuilderData($id);
        $steps = ['personal','summary','experience','education','skills','languages','social_links','custom_sections','references'];
        $p = [];
        foreach ($steps as $s) {
            $v = $d[$s] ?? [];
            $p[$s] = $s === 'skills' ? (!empty($v['technical'])||!empty($v['soft'])) : (in_array($s,['languages','social_links','custom_sections','references']) ? is_array($v)&&count($v)>0 : !empty($v));
        }
        jsonResponse(['success' => true, 'progress' => $p, 'total_steps' => count($steps), 'completed_steps' => count(array_filter($p))]);
    }

    /** POST /api/cv/guest/builder/{id}/complete — Complete guest builder */
    public static function guestCompleteBuilder(string $id): void
    {
        global $mysqli;
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, null)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $data = $cvModel->getBuilderData($id);
        if (empty($data)) { jsonResponse(['error' => 'No builder data found'], 400); return; }
        if (!empty($data['personal']['full_name'])) $cvModel->update($id, ['title' => sanitize_input($data['personal']['full_name']) . "'s CV"]);
        $cvModel->update($id, ['is_active'=>1]);
        jsonResponse(['success'=>true,'message'=>'CV completed successfully!','redirect'=>'/cv-builder/guest/builder/'.$id]);
    }

    /** GET /api/cv/guest/{id}/preview — Preview guest CV */
    public static function guestPreview(string $id): void
    {
        global $twig, $mysqli;
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, null)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $cv = $cvModel->getById($id);
        $bd = $cvModel->getBuilderData($id);
        $personalInfo = [];
        try {
            $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
        } catch (Throwable $e) {}
        $sections = cvBuildSectionsFromBuilderData($bd, $personalInfo);
        $slug = 'minimal';
        try {
            $html = $twig->render('cv/templates/'.$slug.'.twig', ['cv' => $cv, 'sections' => $sections]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Render failed: ' . $e->getMessage()], 500);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /** GET /cv-builder/guest/{id}/export/pdf — Export guest CV as PDF */
    public static function guestExportPdf(string $id): void
    {
        global $twig, $mysqli;
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, null)) { http_response_code(403); echo 'Forbidden'; exit; }
        $cv = $cvModel->getById($id);
        $bd = $cvModel->getBuilderData($id);
        $personalInfo = [];
        try {
            $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
        } catch (Throwable $e) {}
        $sections = cvBuildSectionsFromBuilderData($bd, $personalInfo);
        $slug = 'minimal';
        $html = $twig->render('cv/templates/'.$slug.'.twig', ['cv' => $cv, 'sections' => $sections]);
        require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';
        $pdfTitle = $cv['title'] ?? 'CV';
        $pdfFilename = preg_replace('/[^a-zA-Z0-9_\\-\\x{0980}-\\x{09FF}]/u', '_', $pdfTitle) . '.pdf';
        if (ob_get_level() > 0) ob_clean();
        $mpdfConfig = ['format' => [210, 297], 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 20, 'margin_bottom' => 25, 'margin_header' => 5, 'margin_footer' => 10, 'orientation' => 'P', 'dpi' => 300, 'img_dpi' => 300, 'use_kwt' => true, 'use_substitutions' => true, 'compress' => true];
        $mpdf = mpdf_create_instance($mpdfConfig);
        if (!$mpdf) { http_response_code(500); echo 'Failed to initialize PDF engine'; exit; }
        try {
            mpdf_apply_runtime_optimizations($mpdf);
            $mpdf->SetTitle($pdfTitle); $mpdf->SetAuthor('BroxLab CV Builder'); $mpdf->SetSubject('Curriculum Vitae'); $mpdf->SetKeywords('CV, resume, curriculum vitae');
            $mpdf->SetHTMLHeader('<div style="text-align:right;font-size:8pt;color:#888;border-bottom:1px solid #ddd;padding-bottom:3px;">' . htmlspecialchars($pdfTitle) . '</div>');
            $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt;color:#888;border-top:1px solid #ddd;padding-top:3px;">Page {PAGENO} of {nbpg}</div>');
            $html = mpdf_optimize_html($html); $mpdf->WriteHTML($html);
            $dest = \Mpdf\Output\Destination::DOWNLOAD;
            $mpdf->Output($pdfFilename, $dest); exit;
        } catch (\Throwable $e) {
            logError('Guest PDF Export failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'Failed to generate PDF: ' . $e->getMessage();
            exit;
        }
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // AI FEATURES (merged from CvAiController)
    // ═════════════════════════════════════════════════════════════════════════════

    /** POST /cv-builder/{id}/ai/cover-letter */
    public static function aiCoverLetter(string $id): void {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvRateLimitModel=new CvRateLimitModel($mysqli);
        try{$rl=$cvRateLimitModel->checkRateLimit($userId,'ai_cover_letter');if(!$rl['allowed']){jsonResponse(['error'=>'Rate limit exceeded. You can generate '.$rl['remaining'].' more cover letters.','remaining'=>$rl['remaining'],'reset_at'=>$rl['reset_at']],429);return;}}catch(Exception$e){$rl=['remaining'=>999,'reset_at'=>time()+3600];}
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $input=json_decode(file_get_contents('php://input'),true);
        $company=sanitize_input($input['company_name']??'');$job=sanitize_input($input['job_title']??'');$desc=sanitize_input($input['job_description']??'');
        if(empty($company)||empty($job)){jsonResponse(['error'=>'Company name and job title are required'],400);return;}
        $bd=$cvModel->getBuilderData($id);
        $cvData=[
            'summary'=>$bd['summary']['professional_summary']??'',
            'experience'=>$bd['experience']??[],
            'education'=>$bd['education']??[],
            'skills'=>array_merge(
                is_array($bd['skills']['technical']??null)?$bd['skills']['technical']:[],
                is_array($bd['skills']['soft']??null)?$bd['skills']['soft']:[]
            ),
            'personal'=>$bd['personal']??[],
        ];
        require_once dirname(__DIR__,1).'/Helpers/CvAiHelper.php';
        $result=(new CvAiHelper($mysqli))->generateCoverLetter($cvData,$company,$job,$desc);
        logActivity("Cover Letter Generated","cv",$id,['company'=>$company,'job_title'=>$job],'success');
        jsonResponse($result);
    }

    /** POST /cv-builder/{id}/ai/improve */
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

    /** POST /cv-builder/{id}/ai/ats-score */
    public static function aiAtsScore(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        $cvRateLimitModel=new CvRateLimitModel($mysqli);
        try{$rl=$cvRateLimitModel->checkRateLimit($userId,'ai_ats_score');if(!$rl['allowed']){jsonResponse(['error'=>'Rate limit exceeded','remaining'=>$rl['remaining'],'reset_at'=>$rl['reset_at']],429);}}catch(Exception$e){$rl=['remaining'=>999,'reset_at'=>time()+3600];}
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $bd=$cvModel->getBuilderData($id);
        $cvData=[
            'summary'=>$bd['summary']['professional_summary']??'',
            'experience'=>$bd['experience']??[],
            'education'=>$bd['education']??[],
            'skills'=>array_map(function($s){return is_string($s)?$s:($s['name']??'');},
                array_merge(
                    is_array($bd['skills']['technical']??null)?$bd['skills']['technical']:[],
                    is_array($bd['skills']['soft']??null)?$bd['skills']['soft']:[]
                )
            ),
        ];
        require_once dirname(__DIR__,1).'/Helpers/CvAiHelper.php';
        $result=(new CvAiHelper($mysqli))->calculateAtsScore($cvData);
        header('X-RateLimit-Remaining: '.$rl['remaining']);header('X-RateLimit-Reset: '.$rl['reset_at']);
        jsonResponse($result);
    }

    /** POST /cv-builder/bulk/delete */
    public static function bulkDelete(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvModel=new CvModel($mysqli);
        $d=json_decode(file_get_contents('php://input'),true);
        $ids=$d['cv_ids']??[];
        if(empty($ids)){jsonResponse(['error'=>'No CV IDs provided'],400);}
        $deleted=[];$failed=[];
        foreach($ids as$id){$id=(int)$id;if(!$cvModel->belongsToUser($id,$userId)){$failed[]=['id'=>$id,'reason'=>'Forbidden'];continue;}
            if($cvModel->delete($id)){$deleted[]=$id;logActivity("CV Bulk Deleted","cv",$id,[],'success');}else{$failed[]=['id'=>$id,'reason'=>'Delete failed'];}
        }
        jsonResponse(['success'=>true,'deleted'=>$deleted,'failed'=>$failed,'total_deleted'=>count($deleted),'total_failed'=>count($failed)]);
    }

    /** POST /cv-builder/bulk/export */
    public static function bulkExport(): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $cvModel=new CvModel($mysqli);
        $d=json_decode(file_get_contents('php://input'),true);
        $ids=$d['cv_ids']??[];
        $template=$d['template']??'modern';
        if(empty($ids)){jsonResponse(['error'=>'No CV IDs provided'],400);}
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($template,null,$t,'modern');
        $exports=[];
        foreach($ids as$id){$id=(int)$id;if(!$cvModel->belongsToUser($id,$userId))continue;
            $cv=$cvModel->getById($id);
            $bd = $cvModel->getBuilderData($id);
            $personalInfo = [];
            try {
                $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
            } catch (Throwable $e) {}
            $sections = cvBuildSectionsFromBuilderData($bd, $personalInfo);
            $visible=array_filter($sections,fn($s)=>$s['is_visible']);
            $exports[]=['cv_id'=>$id,'title'=>$cv['title'],'html'=>$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible])];
        }
        jsonResponse(['success'=>true,'exports'=>$exports,'total'=>count($exports)]);
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // BUILDER API (merged from CvBuilderController)
    // ═════════════════════════════════════════════════════════════════════════════

    /** POST /api/cv/builder/{id}/step */
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
        // When all_data is provided, save the complete STATE.data to builder_data.
        // This ensures top-level keys like experience, education, skills, languages,
        // social_links, custom_sections, and references are persisted (not just the
        // step-level key which may only contain { _combined: true } for multi-section steps).
        $allData = $input['all_data'] ?? [];
        if (!empty($allData)) {
            array_walk_recursive($allData, function (&$v) { if (is_string($v)) $v = sanitize_input($v); });
            jsonResponse($cvModel->update($id, ['builder_data' => $allData])
                ? ['success' => true, 'message' => 'Step saved']
                : ['error' => 'Failed to save step']);
        } else {
            jsonResponse($cvModel->saveBuilderStep($id, $step, $sd) ? ['success' => true, 'message' => 'Step saved'] : ['error' => 'Failed to save step']);
        }
    }

    /** GET /api/cv/builder/{id}/progress */
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

    /** POST /api/cv/builder/{id}/complete */
    public static function completeBuilder(string $id): void
    {
        global $mysqli;
        $userId = requireAuth();
        $id = (int)$id;
        $cvModel = new CvModel($mysqli);
        if (!$cvModel->belongsToUser($id, $userId)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
        $input = json_decode(file_get_contents('php://input'), true);
        $requestedTemplate = is_array($input) ? ($input['template'] ?? null) : null;
        // Accept all_data from the request to persist top-level builder keys
        // (experience, education, skills, languages, social_links, custom_sections, references)
        // that were stored at STATE.data top-level but may not have been fully saved to builder_data
        // during individual step saves (since multi-section steps return { _combined: true }).
        $allData = $input['all_data'] ?? [];
        if (!empty($allData)) {
            array_walk_recursive($allData, function (&$v) { if (is_string($v)) $v = sanitize_input($v); });
            $cvModel->update($id, ['builder_data' => $allData]);
            $data = $allData;
        } else {
            $data = $cvModel->getBuilderData($id);
        }
        if (empty($data)) { jsonResponse(['error' => 'No builder data found'], 400); return; }
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
        // Save personal info to structured columns (reliable server-side, not dependent on async JS)
        if (!empty($data['personal'])) {
            $personalData = CvPersonalInfoModel::extractFromBuilderData($data);
            // Filter out empty values to avoid MySQL errors on DATE columns (e.g. date_of_birth='') 
            $personalData = array_filter($personalData, function ($v) { return $v !== '' && $v !== null; });
            if (!empty($personalData)) {
                (new CvPersonalInfoModel($mysqli))->save($userId, $personalData);
            }
        }
        $cvModel->update($id, ['is_active'=>1]);
        logActivity("CV Builder Completed", "cv", $id, [], 'success');
        jsonResponse(['success'=>true,'message'=>'CV completed successfully!','redirect'=>'/cv-builder/'.$id]);
    }

    /** GET /api/cv/{id}/personal-info */
    public static function apiGetPersonalInfo(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel = new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        try {
            $d=(new CvPersonalInfoModel($mysqli))->getById($id);
            jsonResponse($d?['success'=>true,'data'=>['full_name'=>$d['full_name']??'','job_title'=>$d['job_title']??'','email'=>$d['email']??'','phone'=>$d['phone']??'','address'=>$d['address']??'','date_of_birth'=>$d['date_of_birth']??'','nationality'=>$d['nationality']??'','gender'=>$d['gender']??'','driving_license'=>$d['driving_license']??'','website'=>$d['website']??'','linkedin'=>$d['linkedin']??'','github'=>$d['github']??'','twitter'=>$d['twitter']??'','portfolio'=>$d['portfolio']??'','national_id_no'=>$d['national_id_no']??'','passport_no'=>$d['passport_no']??'','birth_certificate_no'=>$d['birth_certificate_no']??'','religion'=>$d['religion']??'']]:['success'=>true,'data'=>null]);
        }catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Failed to load personal info'],500);}
    }

    /** POST /api/cv/{id}/personal-info */
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
            // Filter out empty values to avoid MySQL errors on DATE columns (e.g. date_of_birth='')
            $input = array_filter($input, function ($v) { return $v !== '' && $v !== null; });
            if (!empty($input)) {
                jsonResponse((new CvPersonalInfoModel($mysqli))->save($userId,$input)?['success'=>true,'message'=>'Personal info saved']:['error'=>'Failed to save personal info']);
            } else {
                jsonResponse(['success'=>false,'error'=>'No data to save'],400);
            }
        }catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Save failed: '.$e->getMessage()],500);}
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // EXPORT & PREVIEW (merged from CvExportController)
    // ═════════════════════════════════════════════════════════════════════════════

    /**
     * Helper: Get sections+items array from cv_infos record.
     */
    private static function getSectionsFromCv(array $cv): array
    {
        global $mysqli;
        $bd = [];
        if (!empty($cv['builder_data'])) {
            $bd = json_decode($cv['builder_data'], true) ?: [];
        }
        $personalInfo = [];
        try {
            $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
        } catch (Throwable $e) {}
        return cvBuildSectionsFromBuilderData($bd, $personalInfo);
    }

    /** GET /api/cv/{id}/preview */
    public static function apiPreview(string $id): void {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $cv=$cvModel->getById($id);
        $sections=self::getSectionsFromCv($cv);
        $visible=array_values(array_filter($sections,fn($s)=>$s['is_visible']));
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($_GET['template']??null,$cv['template']??null,$t,'modern');
        $zoom=max(0.5,min(2.0,(float)($_GET['zoom']??1.0)));
        try{$html=$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible]);}catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Render failed: '.$e->getMessage()],500);return;}
        header('Content-Type:text/html;charset=utf-8');
        echo cvRenderA4PreviewHtml($html,$slug,$cv['id'],$zoom,(int)($cv['completion_score']??0));
        exit;
    }

    /** GET /cv-builder/{id}/preview */
    public static function preview(string $id): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);}
        $cv=$cvModel->getById($id);
        $sections=self::getSectionsFromCv($cv);
        $visible=array_filter($sections,fn($s)=>$s['is_visible']);
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($_GET['template']??null,$cv['template']??null,$t,'modern');
        $zoom=max(0.5,min(2.0,(float)($_GET['zoom']??1.0)));
        try{$html=$twig->render('cv/templates/'.$slug.'.twig',['cv'=>$cv,'sections'=>$visible]);}catch(Throwable$e){jsonResponse(['success'=>false,'error'=>'Render failed: '.$e->getMessage()],500);return;}
        if(!empty($_GET['print'])){echo$html;exit;}
        echo cvRenderA4PreviewHtml($html,$slug,$cv['id'],$zoom,(int)($cv['completion_score']??0));
        exit;
    }

    /** GET /cv-builder/{id}/export */
    public static function redirectExport(string $id): void
    {
        header('Location: /cv-builder/'.(int)$id.'/export/pdf');
        exit;
    }

    /** GET /cv-builder/{id}/export/pdf */
    public static function exportPdf(string $id): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){http_response_code(403);echo'Forbidden';exit;}
        $cv=$cvModel->getById($id);
        $sections=self::getSectionsFromCv($cv);
        $visible=array_filter($sections,fn($s)=>$s['is_visible']);
        $t=cvGetTemplateAllowlist();
        $slug=cvResolveTemplate($_GET['template']??null,$cv['template']??null,$t,'modern');
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

    /** GET /cv-builder/{id}/export/docx */
    public static function exportDocx(string $id): void
    {
        global $twig, $mysqli;
        $userId=requireAuth();
        $id=(int)$id;
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){http_response_code(403);echo'Forbidden';exit;}
        $cv=$cvModel->getById($id);
        $sections=self::getSectionsFromCv($cv);
        $visible=array_filter($sections,fn($s)=>$s['is_visible']);
        require_once dirname(__DIR__,1).'/Helpers/DocxHelper.php';
        cvGenerateDocx($cv,$visible,$cv['title'].'.docx');
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // PURCHASES & PAYMENTS (merged from CvPurchaseController)
    // ═════════════════════════════════════════════════════════════════════════════

    /** GET /api/cv/templates/purchased/{slug} */
    public static function checkPurchased(string $slug): void {
        global $mysqli;
        $userId=requireAuth();
        $slug=sanitize_input($slug);
        $stmt=$mysqli->prepare("SELECT id, status FROM cv_template_purchases WHERE user_id = ? AND template_slug = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('is',$userId,$slug);
        $stmt->execute();
        $purchased=$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if($purchased&&$purchased['status']==='completed'){jsonResponse(['success'=>true,'purchased'=>true,'purchase_id'=>(int)$purchased['id']]);}
        else if($purchased&&$purchased['status']==='pending'){jsonResponse(['success'=>true,'purchased'=>false,'pending'=>true,'message'=>'Payment pending admin confirmation']);}
        else{jsonResponse(['success'=>true,'purchased'=>false]);}
    }

    /** GET /api/cv/templates/my-purchases */
    public static function myPurchases(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $stmt=$mysqli->prepare("SELECT template_slug, amount, payment_method, status, created_at, confirmed_at FROM cv_template_purchases WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
        $stmt->bind_param('i',$userId);
        $stmt->execute();
        $purchases=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        jsonResponse(['success'=>true,'purchases'=>$purchases]);
    }

    /** POST /api/cv/templates/initiate-purchase */
    public static function initiatePurchase(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $input=json_decode(file_get_contents('php://input'),true);
        $slug=sanitize_input($input['template_slug']??'');
        $method=strtolower(sanitize_input($input['payment_method']??''));
        $phone=sanitize_input($input['phone_number']??'');
        if(!in_array($method,['bkash','nagad','rocket'],true)){jsonResponse(['error'=>'Invalid payment method'],400);return;}
        $premiumSlugs=['executive'];
        if(!in_array($slug,$premiumSlugs,true)){jsonResponse(['error'=>'Invalid template'],400);return;}
        $checkStmt=$mysqli->prepare("SELECT id, status FROM cv_template_purchases WHERE user_id = ? AND template_slug = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
        $checkStmt->bind_param('is',$userId,$slug);
        $checkStmt->execute();
        $existing=$checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if($existing){
            if($existing['status']==='completed'){jsonResponse(['error'=>'Already purchased','purchased'=>true],409);return;}
            if($existing['status']==='pending'){jsonResponse(['error'=>'Pending purchase exists','pending'=>true],409);return;}
        }
        $insertStmt=$mysqli->prepare("INSERT INTO cv_template_purchases (user_id, template_slug, amount, currency, payment_method, phone_number, status) VALUES (?, ?, 50.00, 'BDT', ?, ?, 'pending')");
        $insertStmt->bind_param('isss',$userId,$slug,$method,$phone);
        $ok=$insertStmt->execute();
        $purchaseId=$insertStmt->insert_id;
        $insertStmt->close();
        if(!$ok||!$purchaseId){jsonResponse(['error'=>'Failed to create purchase'],500);return;}
        logActivity("Purchase Initiated","cv-template-purchase",$purchaseId,['template'=>$slug,'method'=>$method,'amount'=>50],'success');
        jsonResponse(['success'=>true,'purchase_id'=>$purchaseId,'amount'=>50,'merchant_numbers'=>['bkash'=>'01XXXXXXXXX','nagad'=>'01XXXXXXXXX','rocket'=>'01XXXXXXXXX']]);
    }

    /** POST /api/cv/templates/verify-purchase */
    public static function verifyPurchase(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $input=json_decode(file_get_contents('php://input'),true);
        $purchaseId=(int)($input['purchase_id']??0);
        $trxId=sanitize_input($input['transaction_id']??'');
        if($purchaseId<=0||$trxId===''){jsonResponse(['error'=>'Purchase ID and transaction ID are required'],400);return;}
        $stmt=$mysqli->prepare("SELECT id, user_id, status FROM cv_template_purchases WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i',$purchaseId);
        $stmt->execute();
        $purchase=$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if(!$purchase){jsonResponse(['error'=>'Purchase not found'],404);return;}
        if((int)$purchase['user_id']!==$userId){jsonResponse(['error'=>'Forbidden'],403);return;}
        if($purchase['status']!=='pending'){jsonResponse(['error'=>'Purchase is already '.$purchase['status']],400);return;}
        $updateStmt=$mysqli->prepare("UPDATE cv_template_purchases SET transaction_id = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param('si',$trxId,$purchaseId);
        $ok=$updateStmt->execute();$updateStmt->close();
        if(!$ok){jsonResponse(['error'=>'Failed to update purchase'],500);return;}
        logActivity("Payment Submitted","cv-template-purchase",$purchaseId,['transaction_id'=>$trxId],'success');
        jsonResponse(['success'=>true,'message'=>'Transaction ID submitted. Admin will confirm.']);
    }

    /** GET /cv-builder/purchased-templates */
    public static function purchasedTemplates(): void
    {
        global $twig, $mysqli;
        $userId = requireAuth();

        $stmt = $mysqli->prepare(
            "SELECT id, template_slug, amount, currency, payment_method, transaction_id, status, created_at, confirmed_at 
             FROM cv_template_purchases WHERE user_id = ? AND deleted_at IS NULL 
             ORDER BY created_at DESC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $cvModel = new CvModel($mysqli);
        $cvs = $cvModel->getByUserId($userId);
        $templateUsage = [];
        foreach ($cvs as $cv) {
            $t = $cv['template'] ?? 'modern';
            $templateUsage[$t] = ($templateUsage[$t] ?? 0) + 1;
        }

        $templates = [
            'creative' => ['name' => 'Creative Portfolio', 'gradient' => 'linear-gradient(135deg, #EC4899, #F97316)', 'icon' => 'palette'],
            'classic' => ['name' => 'Classic Traditional', 'gradient' => 'linear-gradient(135deg, #1B2A4A, #2D3B5C)', 'icon' => 'book'],
            'technical' => ['name' => 'Technical Engineer', 'gradient' => 'linear-gradient(135deg, #0F172A, #0F766E)', 'icon' => 'terminal'],
            'executive' => ['name' => 'Executive Elite', 'gradient' => 'linear-gradient(135deg, #1A1A2E, #16213E)', 'icon' => 'crown'],
        ];

        echo $twig->render('cv/purchased-templates.twig', [
            'purchases' => $purchases,
            'template_usage' => $templateUsage,
            'templates' => $templates,
            'page_title' => 'My Purchased Templates',
            'breadcrumbs' => [
                ['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'],
                ['label' => 'Purchased Templates', 'icon' => 'crown']
            ]
        ]);
    }

    /** POST /api/cv/templates/bkash-checkout */
    public static function bkashCheckout(): void
    {
        global $mysqli;
        $userId = requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $slug = sanitize_input($input['template_slug'] ?? '');
        $phone = sanitize_input($input['phone_number'] ?? '');
        if (empty($slug)) { jsonResponse(['error' => 'Template slug is required'], 400); return; }
        if (empty($phone)) { jsonResponse(['error' => 'Phone number is required'], 400); return; }
        require_once dirname(__DIR__, 1) . '/Services/CvPaymentService.php';
        $paymentService = new CvPaymentService($mysqli);
        $result = $paymentService->initiateBkashCheckout($userId, $slug, $phone);
        if (!$result['success']) { jsonResponse(['error' => $result['error']], 400); return; }
        jsonResponse(['success' => true] + $result);
    }

    /** GET/POST /payments/cv/bkash/callback */
    public static function bkashCallback(): void
    {
        global $mysqli;
        require_once dirname(__DIR__, 1) . '/Services/CvPaymentService.php';
        $paymentService = new CvPaymentService($mysqli);
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $result = $paymentService->handleCallbackRedirect($_GET);
            if ($result['success']) {
                $purchaseId = $result['purchase_id'] ?? 0;
                $trxId = $result['transaction_id'] ?? '';
                $slug = '';
                $slugStmt = $mysqli->prepare("SELECT template_slug FROM cv_template_purchases WHERE id = ?");
                if ($slugStmt) {
                    $slugStmt->bind_param('i', $purchaseId);
                    $slugStmt->execute();
                    $slugRow = $slugStmt->get_result()->fetch_assoc();
                    $slug = $slugRow['template_slug'] ?? '';
                    $slugStmt->close();
                }
                header('Location: /cv-builder/templates?payment=success&purchase_id=' . $purchaseId . '&trxid=' . urlencode($trxId) . '&template_slug=' . urlencode($slug));
                exit;
            }
            $status = $result['status'] ?? 'failed';
            header('Location: /cv-builder/templates?payment=' . urlencode($status));
            exit;
        }
        $input = [];
        $raw = file_get_contents('php://input');
        if ($raw) { $decoded = json_decode($raw, true); if (is_array($decoded)) $input = $decoded; }
        $input = array_merge($_POST ?? [], $input, $_GET ?? []);
        $result = $paymentService->handleBkashCallback($input);
        http_response_code($result['code'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    /** POST /api/cv/{id}/migrate-to-v3 */
    public static function migrateToV3(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();$id=(int)$id;
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $builderData=$cvModel->getBuilderData($id);
        if(empty($builderData)){jsonResponse(['error'=>'No builder_data found'],400);return;}
        $cv=$cvModel->getById($id);$template=$cv['template']??'modern';
        try {
            require_once dirname(__DIR__,1).'/Services/CvProfileService.php';
            $profileService=new CvProfileService($mysqli);
            $profileId=$profileService->migrateFromBuilderData($id,$userId,$builderData,$template);
            if($profileId){jsonResponse(['success'=>true,'message'=>'CV migrated','profile_id'=>$profileId]);}
            else{jsonResponse(['error'=>'Migration failed'],500);}
        }catch(\Throwable$e){jsonResponse(['error'=>'Migration failed: '.$e->getMessage()],500);}
    }

    /** POST /api/cv/migrate-all-to-v3 */
    public static function migrateAllToV3(): void
    {
        global $mysqli;
        $userId=requireAuth();
        $cvModel=new CvModel($mysqli);
        $cvs=$cvModel->getByUserId($userId);
        $migrated=0;$failed=0;$errors=[];
        require_once dirname(__DIR__,1).'/Services/CvProfileService.php';
        $profileService=new CvProfileService($mysqli);
        foreach($cvs as $cv){
            $id=(int)$cv['id'];$builderData=$cvModel->getBuilderData($id);
            if(empty($builderData))continue;
            try{$template=$cv['template']??'modern';$result=$profileService->migrateFromBuilderData($id,$userId,$builderData,$template);if($result)$migrated++;else{$failed++;$errors[]=['cv_id'=>$id,'error'=>'returned null'];}}catch(\Throwable$e){$failed++;$errors[]=['cv_id'=>$id,'error'=>$e->getMessage()];}
        }
        jsonResponse(['success'=>true,'message'=>"Migrated {$migrated} CV(s), {$failed} failed",'migrated'=>$migrated,'failed'=>$failed,'errors'=>$errors]);
    }

    /** POST /api/cv/{id}/photo */
    public static function uploadPhoto(string $id): void
    {
        try {
            global $mysqli;
            $userId=requireAuth();$id=(int)$id;
            $cvModel=new CvModel($mysqli);
            if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
            if(empty($_FILES['photo'])||$_FILES['photo']['error']!==UPLOAD_ERR_OK){
                $errCode=empty($_FILES['photo'])?-1:$_FILES['photo']['error'];
                jsonResponse(['error'=>'No file uploaded','upload_error'=>$errCode],400);return;
            }
            $f=$_FILES['photo'];
            $allowed=['image/jpeg','image/png','image/webp','image/gif'];
            if(!in_array($f['type'],$allowed)){jsonResponse(['error'=>'Only JPG, PNG, WebP, GIF allowed','got'=>$f['type']],400);return;}
            if($f['size']>5*1024*1024){jsonResponse(['error'=>'File too large (max 5MB)','size'=>$f['size']],400);return;}
            $dir = defined('UPLOADS_CV_PHOTOS_DIR') ? UPLOADS_CV_PHOTOS_DIR : (dirname(__DIR__,2).'/public_html/uploads/cv-photos');
            if(!is_dir($dir)){@mkdir($dir,0755,true);}
            if(!is_dir($dir)){jsonResponse(['error'=>'Upload directory not writable'],500);return;}
            $ext=pathinfo($f['name'],PATHINFO_EXTENSION);
            $filename='cv_'.$id.'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
            if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$filename)){jsonResponse(['error'=>'Failed to save file'],500);return;}
            $path='/uploads/cv-photos/'.$filename;
            $cvModel->update($id,['profile_photo'=>$path]);
            logActivity("CV Photo Uploaded","cv",$id,['filename'=>$filename],'success');
            jsonResponse(['success'=>true,'message'=>'Photo uploaded','photo_url'=>$path]);
        } catch(\Throwable $e) {
            logError('CV Photo Upload Error: '.$e->getMessage(),'error',['cv_id'=>$id,'trace'=>$e->getTraceAsString()]);
            jsonResponse(['error'=>'Upload failed: '.$e->getMessage()],500);
        }
    }

    /** DELETE /api/cv/{id}/photo */
    public static function deletePhoto(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();$id=(int)$id;
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $cv=$cvModel->getById($id);
        if(!$cv){jsonResponse(['error'=>'CV not found'],404);return;}
        if(!empty($cv['profile_photo'])){
            $photoDir = defined('UPLOADS_CV_PHOTOS_DIR') ? UPLOADS_CV_PHOTOS_DIR : (dirname(__DIR__,2).'/public_html/uploads/cv-photos');
            $fp = $photoDir . '/' . basename($cv['profile_photo']);
            if(file_exists($fp))unlink($fp);
        }
        $cvModel->update($id,['profile_photo'=>null]);
        jsonResponse(['success'=>true,'message'=>'Photo removed']);
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // TEMPLATE FAVORITES API (merged from AdminCvTemplatesController)
    // ═════════════════════════════════════════════════════════════════════════════

    /** POST /api/cv/templates/favorite */
    public static function apiCvTemplateFavorite(): void
    {
        global $mysqli;
        $userId = getCurrentUserId();
        if (!$userId) { jsonResponse(['error' => 'Unauthorized'], 401); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        $slug = sanitize_input($data['slug'] ?? '');
        if (empty($slug)) { jsonResponse(['error' => 'Slug required'], 400); return; }

        $ts = new CvTemplateService($mysqli);
        $template = $ts->getBySlug($slug);
        if (!$template) { jsonResponse(['error' => 'Template not found'], 404); return; }

        $stmt = $mysqli->prepare("SELECT id, is_favorite FROM user_cv_templates WHERE user_id = ? AND template_id = ?");
        $stmt->bind_param('ii', $userId, $template['id']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $nf = $existing['is_favorite'] ? 0 : 1;
            $up = $mysqli->prepare("UPDATE user_cv_templates SET is_favorite = ? WHERE id = ?");
            $up->bind_param('ii', $nf, $existing['id']); $up->execute();
        } else {
            $ins = $mysqli->prepare("INSERT INTO user_cv_templates (user_id, profile_id, template_id, is_favorite) VALUES (?, 0, ?, 1)");
            $ins->bind_param('ii', $userId, $template['id']); $ins->execute();
            $nf = 1;
        }
        jsonResponse(['success' => true, 'is_favorite' => (bool)$nf]);
    }

    /** GET /api/cv/templates/favorites */
    public static function apiCvTemplateFavorites(): void
    {
        global $mysqli;
        $userId = getCurrentUserId();
        if (!$userId) { jsonResponse(['error' => 'Unauthorized'], 401); return; }
        $ts = new CvTemplateService($mysqli);
        $stmt = $mysqli->prepare("SELECT t.*, uct.is_favorite FROM user_cv_templates uct JOIN cv_templates t ON uct.template_id = t.id WHERE uct.user_id = ? AND uct.is_favorite = 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $templates = [];
        while ($row = $result->fetch_assoc()) $templates[] = $ts->decodeJsonFields($row);
        jsonResponse(['success' => true, 'templates' => $templates, 'slugs' => array_column($templates, 'slug')]);
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

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId(): ?int
    {
        return AuthManager::getCurrentUserId();
    }
}

if (!function_exists('isAdminUser')) {
    function isAdminUser(): bool
    {
        global $userModel;
        $userId = getCurrentUserId();
        if (!$userId) return false;
        try {
            return $userModel && ($userModel->isSuperAdmin($userId) || $userModel->hasRole($userId, 'admin'));
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('cvGetTemplateAllowlist')) {
    function cvGetTemplateAllowlist(): array
    {
        $userId = getCurrentUserId();
        
        // Admin sees all templates (including disabled)
        if ($userId && function_exists('isAdminUser') && isAdminUser()) {
            $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
            $files = glob($dir . '/*.twig') ?: [];
            $templates = [];
            foreach ($files as $file) {
                $name = basename($file, '.twig');
                if ($name === '' || $name[0] === '_') continue;
                $templates[] = $name;
            }
            $templates = array_values(array_unique($templates));
            sort($templates);
            return $templates;
        }
        
        // Guest (unauthenticated) sees only 'minimal'
        if (!$userId) {
            $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
            $files = glob($dir . '/*.twig') ?: [];
            $allTemplates = [];
            foreach ($files as $file) {
                $name = basename($file, '.twig');
                if ($name === '' || $name[0] === '_') continue;
                $allTemplates[] = $name;
            }
            // Only allow 'minimal' for guests, if it exists
            if (in_array('minimal', $allTemplates, true)) {
                return ['minimal'];
            }
            return [];
        }
        
        // Authenticated regular users: normal behavior
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

if (!function_exists('cvBuilderSectionBlueprints')) {
    function cvBuilderSectionBlueprints(): array
    {
        return [
            'summary' => ['title' => 'Summary', 'steps' => ['personal', 'summary']],
            'experience' => ['title' => 'Work Experience', 'steps' => ['experience']],
            'education' => ['title' => 'Education', 'steps' => ['education']],
            'skills' => ['title' => 'Skills', 'steps' => ['skills']],
            'languages' => ['title' => 'Languages', 'steps' => ['languages']],
            'social_links' => ['title' => 'Social Links', 'steps' => ['social_links']],
            'custom_sections' => ['title' => 'Custom Sections', 'steps' => ['custom_sections']],
            'references' => ['title' => 'References', 'steps' => ['references']],
        ];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// ROUTE REGISTRATION
// ═════════════════════════════════════════════════════════════════════════════
global $router;

// ── Template Marketplace (public, no auth) ──
$router->get('/cv-builder/templates', ['CvController', 'marketplace']);

// ── Guest CV Builder (no auth required, minimal template only) ──
$router->get('/cv-builder/guest', ['CvController', 'guestDashboard']);
$router->get('/cv-builder/guest/builder/{id}', ['CvController', 'guestBuilder']);
$router->post('/cv-builder/guest', ['middleware' => ['csrf']], ['CvController', 'guestStore']);
$router->post('/api/cv/guest/builder/{id}/step', ['middleware' => ['csrf']], ['CvController', 'guestSaveStep']);
$router->get('/api/cv/guest/builder/{id}/progress', ['CvController', 'guestBuilderProgress']);
$router->post('/api/cv/guest/builder/{id}/complete', ['middleware' => ['csrf']], ['CvController', 'guestCompleteBuilder']);
$router->get('/api/cv/guest/{id}/preview', ['CvController', 'guestPreview']);
$router->get('/cv-builder/guest/{id}/export/pdf', ['CvController', 'guestExportPdf']);

// ── Guest CV Claiming & Upgrade (authenticated) ──
$router->post('/api/cv/claim-guest-cvs', ['middleware' => ['auth', 'csrf']], ['CvController', 'claimGuestCvs']);
$router->get('/api/cv/has-guest-cvs', ['middleware' => ['auth']], ['CvController', 'hasGuestCvs']);
$router->get('/api/cv/my-cvs', ['middleware' => ['auth']], ['CvController', 'myCvs']);
$router->post('/api/cv/{id}/upgrade-template', ['middleware' => ['auth', 'csrf']], ['CvController', 'upgradeTemplate']);

// ── CV Dashboard ──
$router->get('/cv-builder', ['middleware' => ['auth']], ['CvController', 'dashboard']);

// ── Create New CV Page ──
$router->get('/cv-builder/new', ['middleware' => ['auth']], ['CvController', 'createForm']);

// ── Builder Wizard ──
$router->get('/cv-builder/builder/{id}', ['middleware' => ['auth']], ['CvController', 'builder']);

// ── CRUD ──
$router->post('/cv-builder', ['middleware' => ['auth', 'csrf']], ['CvController', 'store']);
$router->get('/cv-builder/form-data', ['middleware' => ['auth']], ['CvController', 'formData']);
$router->post('/cv-builder/save', ['middleware' => ['auth', 'csrf']], ['CvController', 'save']);

// ── Redirect Shortcuts ──
$router->get('/cv-builder/download', ['middleware' => ['auth']], ['CvController', 'redirectDownload']);
$router->get('/cv-builder/share', ['middleware' => ['auth']], ['CvController', 'redirectShare']);
$router->get('/cv-builder/view', ['middleware' => ['auth']], ['CvController', 'redirectView']);

// ── CV Detail / Update / Duplicate / Delete ──
$router->get('/cv-builder/{id}', ['middleware' => ['auth']], ['CvController', 'redirectToBuilder']);
$router->put('/cv-builder/{id}', ['middleware' => ['auth', 'csrf']], ['CvController', 'update']);
$router->post('/cv-builder/{id}/update', ['middleware' => ['auth', 'csrf']], ['CvController', 'updateForm']);
$router->post('/cv-builder/{id}/duplicate', ['middleware' => ['auth', 'csrf']], ['CvController', 'duplicate']);
$router->delete('/cv-builder/{id}', ['middleware' => ['auth', 'csrf']], ['CvController', 'delete']);
$router->delete('/api/cv/{id}', ['middleware' => ['auth', 'csrf']], ['CvController', 'delete']);

// ── Template Preview API (public, no auth) ──
$router->get('/api/cv/templates/{slug}/preview', ['CvController', 'templatePreview']);

// ── Rate Limits ──
$router->get('/cv-builder/rate-limits', ['middleware' => ['auth']], ['CvController', 'rateLimits']);

// ── AI Features (merged from CvAiController) ──
$router->post('/cv-builder/{id}/ai/cover-letter', ['middleware'=>['auth','csrf']], ['CvController', 'aiCoverLetter']);
$router->post('/cv-builder/{id}/ai/improve', ['middleware'=>['auth','csrf']], ['CvController', 'aiImprove']);
$router->post('/cv-builder/{id}/ai/ats-score', ['middleware'=>['auth','csrf']], ['CvController', 'aiAtsScore']);

// ── Bulk Operations (merged from CvAiController) ──
$router->post('/cv-builder/bulk/delete', ['middleware'=>['auth','csrf']], ['CvController', 'bulkDelete']);
$router->post('/cv-builder/bulk/export', ['middleware'=>['auth','csrf']], ['CvController', 'bulkExport']);

// ── Builder API (merged from CvBuilderController) ──
$router->post('/api/cv/builder/{id}/step', ['middleware' => ['auth', 'csrf']], ['CvController', 'saveBuilderStep']);
$router->get('/api/cv/builder/{id}/progress', ['middleware' => ['auth']], ['CvController', 'builderProgress']);
$router->post('/api/cv/builder/{id}/complete', ['middleware' => ['auth', 'csrf']], ['CvController', 'completeBuilder']);

// ── Personal Info API (merged from CvBuilderController) ──
$router->get('/api/cv/{id}/infos', ['middleware'=>['auth']], ['CvController', 'apiGetPersonalInfo']);
$router->post('/api/cv/{id}/infos', ['middleware'=>['auth','csrf']], ['CvController', 'apiSavePersonalInfo']);

// ── Preview & Export (merged from CvExportController) ──
$router->get('/api/cv/{id}/preview', ['middleware'=>['auth']], ['CvController', 'apiPreview']);
$router->get('/cv-builder/{id}/preview', ['middleware'=>['auth']], ['CvController', 'preview']);
$router->get('/cv-builder/{id}/export', ['middleware'=>['auth']], ['CvController', 'redirectExport']);
$router->get('/cv-builder/{id}/export/pdf', ['middleware'=>['auth']], ['CvController', 'exportPdf']);
$router->get('/cv-builder/{id}/export/docx', ['middleware'=>['auth']], ['CvController', 'exportDocx']);

// ── V3 Write-Through Bridge (merged from CvPurchaseController) ──
$router->post('/api/cv/{id}/migrate-to-v3', ['middleware' => ['auth', 'csrf']], ['CvController', 'migrateToV3']);
$router->post('/api/cv/migrate-all-to-v3', ['middleware' => ['auth', 'csrf']], ['CvController', 'migrateAllToV3']);

// ── Photo Upload (merged from CvPurchaseController) ──
$router->post('/api/cv/{id}/photo', ['middleware'=>['auth']], ['CvController', 'uploadPhoto']);
$router->delete('/api/cv/{id}/photo', ['middleware'=>['auth']], ['CvController', 'deletePhoto']);

// ── Premium Template Purchases (merged from CvPurchaseController) ──
$router->get('/cv-builder/purchased-templates', ['middleware' => ['auth']], ['CvController', 'purchasedTemplates']);
$router->get('/api/cv/templates/purchased/{slug}', ['middleware' => ['auth']], ['CvController', 'checkPurchased']);
$router->get('/api/cv/templates/my-purchases', ['middleware' => ['auth']], ['CvController', 'myPurchases']);
$router->post('/api/cv/templates/initiate-purchase', ['middleware' => ['auth', 'csrf']], ['CvController', 'initiatePurchase']);
$router->post('/api/cv/templates/verify-purchase', ['middleware' => ['auth', 'csrf']], ['CvController', 'verifyPurchase']);

// ── bKash Checkout (merged from CvPurchaseController) ──
$router->post('/api/cv/templates/bkash-checkout', ['middleware' => ['auth', 'csrf']], ['CvController', 'bkashCheckout']);
$router->get('/payments/cv/bkash/callback', ['CvController', 'bkashCallback']);
$router->post('/payments/cv/bkash/callback', ['CvController', 'bkashCallback']);

// ── Template Favorites API (merged from AdminCvTemplatesController) ──
$router->post('/api/cv/templates/favorite', ['middleware' => ['auth', 'csrf']], ['CvController', 'apiCvTemplateFavorite']);
$router->get('/api/cv/templates/favorites', ['middleware' => ['auth']], ['CvController', 'apiCvTemplateFavorites']);

if (!function_exists('cvBuildSectionsFromBuilderData')) {
    /**
     * Convert builder_data JSON into the sections+items array format expected by CV templates.
     * This replaces the old cvMaterializeBuilderData which wrote to cv_sections/cv_items tables.
     *
     * @param array $builderData The builder_data JSON decoded into an array.
     * @param array $personalInfo Optional personal info from cv_infos table.
     * @return array Array of sections, each with 'id', 'title', 'section_type', 'is_visible', 'items'.
     */
    function cvBuildSectionsFromBuilderData(array $builderData, array $personalInfo = []): array
    {
        $sections = [];
        $idx = 0;

        // ── Summary Section ──
        $summaryText = $builderData['summary']['professional_summary'] ?? '';
        $objective = $builderData['summary']['career_objective'] ?? '';
        $personal = $builderData['personal'] ?? [];
        if (!empty($personalInfo)) {
            $personal = array_merge($personal, $personalInfo);
        }
        if (!empty($summaryText) || !empty($objective) || !empty($personal)) {
            $idx++;
            $summaryContent = [
                'full_name' => $personal['full_name'] ?? $personalInfo['full_name'] ?? '',
                'job_title' => $personal['job_title'] ?? '',
                'email' => $personal['email'] ?? $personalInfo['email'] ?? '',
                'phone' => $personal['phone'] ?? $personalInfo['phone'] ?? '',
                'address' => $personal['address'] ?? $personalInfo['address'] ?? '',
                'website' => $personal['website'] ?? $personalInfo['website'] ?? '',
                'linkedin' => $personal['linkedin'] ?? $personalInfo['linkedin'] ?? '',
                'github' => $personal['github'] ?? $personalInfo['github'] ?? '',
                'summary' => $summaryText,
                'objective' => $objective,
                'text' => $summaryText,
            ];
            $sections[] = [
                'id' => $idx,
                'title' => 'Professional Summary',
                'section_type' => 'summary',
                'is_visible' => 1,
                'items' => [['id' => 1, 'content' => array_filter($summaryContent, fn($v) => $v !== '' && $v !== null)]],
            ];
        }

        // ── Experience Section ──
        $experienceEntries = $builderData['experience'] ?? [];
        if (!empty($experienceEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($experienceEntries as $entry) {
                if (empty($entry['company'])) continue;
                $itemId++;
                $items[] = [
                    'id' => $itemId,
                    'content' => [
                        'company' => $entry['company'] ?? '',
                        'position' => $entry['position'] ?? '',
                        'location' => $entry['location'] ?? '',
                        'start_date' => $entry['start_date'] ?? '',
                        'end_date' => $entry['end_date'] ?? '',
                        'is_current' => !empty($entry['is_current']) ? 1 : 0,
                        'description' => $entry['responsibilities'] ?? $entry['description'] ?? '',
                    ],
                ];
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'Work Experience', 'section_type' => 'experience', 'is_visible' => 1, 'items' => $items];
            }
        }

        // ── Education Section ──
        $educationEntries = $builderData['education'] ?? [];
        if (!empty($educationEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($educationEntries as $entry) {
                if (empty($entry['institution'])) continue;
                $itemId++;
                $items[] = [
                    'id' => $itemId,
                    'content' => [
                        'institution' => $entry['institution'] ?? '',
                        'degree' => $entry['degree'] ?? '',
                        'field' => $entry['field'] ?? '',
                        'start_date' => $entry['start_year'] ?? $entry['start_date'] ?? '',
                        'end_date' => $entry['end_year'] ?? $entry['end_date'] ?? '',
                        'gpa' => $entry['gpa'] ?? '',
                    ],
                ];
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'Education', 'section_type' => 'education', 'is_visible' => 1, 'items' => $items];
            }
        }

        // ── Skills Section ──
        $technical = (array)($builderData['skills']['technical'] ?? []);
        $soft = (array)($builderData['skills']['soft'] ?? []);
        if (!empty($technical) || !empty($soft)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($technical as $skill) {
                $skill = trim((string)$skill);
                if ($skill !== '') { $itemId++; $items[] = ['id' => $itemId, 'content' => ['name' => $skill]]; }
            }
            foreach ($soft as $skill) {
                $skill = trim((string)$skill);
                if ($skill !== '') { $itemId++; $items[] = ['id' => $itemId, 'content' => ['name' => $skill]]; }
            }
            $sections[] = ['id' => $idx, 'title' => 'Skills', 'section_type' => 'skills', 'is_visible' => 1, 'items' => $items];
        }

        // ── Languages Section ──
        $langEntries = $builderData['languages'] ?? [];
        if (!empty($langEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($langEntries as $entry) {
                if (empty($entry['name'])) continue;
                $itemId++;
                $items[] = ['id' => $itemId, 'content' => ['name' => $entry['name'], 'proficiency' => $entry['proficiency'] ?? 'intermediate']];
            }
            $sections[] = ['id' => $idx, 'title' => 'Languages', 'section_type' => 'languages', 'is_visible' => 1, 'items' => $items];
        }

        // ── Social Links Section ──
        $socialEntries = $builderData['social_links'] ?? [];
        if (!empty($socialEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($socialEntries as $entry) {
                if (empty($entry['url'])) continue;
                $itemId++;
                $items[] = ['id' => $itemId, 'content' => ['platform' => $entry['platform'] ?? '', 'url' => $entry['url'] ?? '']];
            }
            $sections[] = ['id' => $idx, 'title' => 'Social Links', 'section_type' => 'social_links', 'is_visible' => 1, 'items' => $items];
        }

        // ── Custom Sections ──
        $customEntries = $builderData['custom_sections'] ?? [];
        if (!empty($customEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($customEntries as $entry) {
                if (empty($entry['title'])) continue;
                $itemId++;
                $items[] = ['id' => $itemId, 'content' => ['title' => $entry['title'], 'content' => $entry['content'] ?? '']];
            }
            $sections[] = ['id' => $idx, 'title' => 'Custom Sections', 'section_type' => 'custom_sections', 'is_visible' => 1, 'items' => $items];
        }

        // ── References Section ──
        $refEntries = $builderData['references'] ?? [];
        if (!empty($refEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($refEntries as $entry) {
                if (empty($entry['name'])) continue;
                $itemId++;
                $items[] = ['id' => $itemId, 'content' => ['name' => $entry['name'], 'title' => $entry['title'] ?? '', 'email' => $entry['email'] ?? '', 'phone' => $entry['phone'] ?? '', 'company' => $entry['company'] ?? '']];
            }
            $sections[] = ['id' => $idx, 'title' => 'References', 'section_type' => 'references', 'is_visible' => 1, 'items' => $items];
        }

        return $sections;
    }
}
