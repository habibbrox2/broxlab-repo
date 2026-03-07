<?php


// ------------------ Initialize Purifier (Reusable) ------------------

function getPurifier(): \HTMLPurifier {

    static $purifier = null;

    if ($purifier !== null) {

        return $purifier;

    }

    global $settingsModel;

    $config = \HTMLPurifier_Config::createDefault();

    // Check if HTMLPurifier cache is enabled from app settings
    $cacheEnabled = false;
    if (isset($settingsModel) && $settingsModel instanceof AppSettings) {
        $appSettings = $settingsModel->getSettings();
        if (!empty($appSettings['enable_cache']) && $appSettings['enable_cache'] != '0') {
            $cacheEnabled = true;
        }
    }

    // Only set cache if enabled AND directory is writable
    if ($cacheEnabled) {
        $cacheDir = HTMLPURIFIER_CACHE_DIR;
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        if (is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
            logDebug('HTMLPurifier cache enabled', ['cache_dir' => $cacheDir]);
        } else {
            // Fall back to null cache if directory not writable
            $config->set('Cache.DefinitionImpl', null);
            logError('HTMLPurifier cache directory is not writable: ' . $cacheDir, 'WARNING');
        }
    } else {
        // Disable cache to avoid permission warnings
        $config->set('Cache.DefinitionImpl', null);
    }




    // allow advanced HTML (tables, iframes for video, images, links)

    $config->set('HTML.Allowed',

        'p,br,span,div,b,strong,i,em,u,ul,ol,li,a[href|title|target],'

        .'img[src|alt|width|height],'

        .'table,tr,td,th,thead,tbody,tfoot,'

        .'iframe[src|width|height|frameborder]'

    );



    // enable safe iframe (YouTube / Vimeo only)

    $config->set('HTML.SafeIframe', true);

    $config->set('URI.SafeIframeRegexp',

        '%^(https?:)?//(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%'

    );



    // Custom HTML definition to support allowfullscreen attribute on iframes

    $config->set('HTML.TargetBlank', true);

    $config->set('HTML.DefinitionID', 'custom-html');

    $config->set('HTML.DefinitionRev', 1);



    if ($def = $config->maybeGetRawHTMLDefinition()) {

        $def->addAttribute('iframe', 'allowfullscreen', 'Bool');

    }



    $purifier = new \HTMLPurifier($config);

    return $purifier;

}
