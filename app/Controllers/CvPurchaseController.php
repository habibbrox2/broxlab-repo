<?php
class CvPurchaseController {
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
                header('Location: /cv-builder/templates?payment=success&purchase_id=' . $purchaseId . '&trxid=' . urlencode($trxId));
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
            $dir=dirname(__DIR__,1).'/../uploads/cv-photos';
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

    public static function deletePhoto(string $id): void
    {
        global $mysqli;
        $userId=requireAuth();$id=(int)$id;
        $cvModel=new CvModel($mysqli);
        if(!$cvModel->belongsToUser($id,$userId)){jsonResponse(['error'=>'Forbidden'],403);return;}
        $cv=$cvModel->getById($id);
        if(!$cv){jsonResponse(['error'=>'CV not found'],404);return;}
        if(!empty($cv['profile_photo'])){$fp=dirname(__DIR__,1).'/..'.$cv['profile_photo'];if(file_exists($fp))unlink($fp);}
        $cvModel->update($id,['profile_photo'=>null]);
        jsonResponse(['success'=>true,'message'=>'Photo removed']);
    }
}
