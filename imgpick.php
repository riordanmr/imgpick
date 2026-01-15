<?php
// imgpick.php is a simple image gallery that allows users to click on images
// to have them copied to a directory.
// Mark Riordan  2026-01-12  with GitHub Copilot assistance

// Start session
session_start();

// Load and consolidate all JSON data from firefly_json1 directory - only once per session
if (!isset($_SESSION['fireflyData'])) {
    $jsonDir = 'firefly_json1';
    $consolidatedData = [];

    if (is_dir($jsonDir)) {
        $jsonFiles = glob($jsonDir . '/*.json');
        
        foreach ($jsonFiles as $jsonFile) {
            $jsonContent = file_get_contents($jsonFile);
            if ($jsonContent !== false) {
                $data = json_decode($jsonContent, true);
                
                // Extract assets from the JSON structure
                if (isset($data['data']['homeFolder']['fireflyGenerationsDirectory']['generationAssetsConnection']['assets'])) {
                    $assets = $data['data']['homeFolder']['fireflyGenerationsDirectory']['generationAssetsConnection']['assets'];
                    
                    // Add each asset node to the consolidated data array
                    foreach ($assets as $asset) {
                        if (isset($asset['node'])) {
                            $consolidatedData[] = $asset['node'];
                        }
                    }
                }
            }
        }
    }
    
    // Store consolidated data in session for reuse
    $_SESSION['fireflyData'] = $consolidatedData;
}

// Access the consolidated data from session
$GLOBALS['fireflyData'] = $_SESSION['fireflyData'];

/**
 * Extract rendition ID from filename
 * Format: ...._US_<rendition_id>_version...
 * Example: 1768273906.148771__rendition_id_urn_aaid_sc_US_b7d1c692-6022-4249-b463-65c6faacb903_version=1_size=256_type=image_2Fpng.png
 * Returns: b7d1c692-6022-4249-b463-65c6faacb903
 */
function extract_rendition_id($filename) {
    if (preg_match('/_US_([^_]+)_version/', $filename, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Search for asset data by rendition ID in the consolidated firefly data
 * Returns the asset node containing the matching renditionLink.href
 */
function find_asset_by_rendition_id($renditionId, $fireflyData) {
    foreach ($fireflyData as $asset) {
        if (isset($asset['renditionLink']['href'])) {
            $href = $asset['renditionLink']['href'];
            // Check if the href contains the rendition ID
            if (strpos($href, $renditionId) !== false) {
                return $asset;
            }
        }
    }
    return null;
}

/**
 * Handle image click action
 */
function image_click($filename) {
    $renditionId = extract_rendition_id($filename);
    
    if ($renditionId === null) {
        error_log('Could not extract rendition ID from filename: ' . $filename);
        return [
            'success' => false,
            'message' => 'Could not extract rendition ID from filename',
            'filename' => $filename
        ];
    }
    
    $fireflyData = $_SESSION['fireflyData'] ?? [];
    $asset = find_asset_by_rendition_id($renditionId, $fireflyData);
    
    if ($asset === null) {
        error_log('Asset not found for rendition ID: ' . $renditionId);
        return [
            'success' => false,
            'message' => 'Asset not found for rendition ID: ' . $renditionId,
            'filename' => $filename,
            'renditionId' => $renditionId
        ];
    }
    
    // Extract outputComponents and revision data
    $outputComponents = $asset['appMetadata']['am']['firefly']['outputComponents'] ?? [];
    $renditionLink = $asset['renditionLink']['href'] ?? '';

    error_log('Asset found for rendition ID: ' . $renditionId . '; outputComponents: ' . json_encode($outputComponents));
    
    // Download the image
    $downloadResult = download_image($asset);
    
    return [
        'success' => true,
        'message' => 'Asset found',
        'filename' => $filename,
        'renditionId' => $renditionId,
        'renditionLink' => $renditionLink,
        'outputComponents' => $outputComponents,
        'assetId' => $asset['repo_assetId'] ?? '',
        'assetName' => $asset['repo_name'] ?? '',
        'download' => $downloadResult
    ];
}

/**
 * Download image from Adobe Firefly using asset data
 * 
 * @param array $asset The asset data containing repo_assetId, outputComponents, etc.
 * @return array Result with success status and downloaded filename or error message
 */
function download_image($asset) {
    $outputDir = 'firefly_bloopers';
    
    // Create output directory if it doesn't exist
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $repoAssetId = $asset['repo_assetId'] ?? '';
    if (empty($repoAssetId)) {
        return [
            'success' => false,
            'message' => 'Missing repo_assetId in asset data'
        ];
    }
    
    $assetId = $repoAssetId;
    
    // Get component_id and revision from outputComponents
    $outputComponents = $asset['appMetadata']['am']['firefly']['outputComponents'] ?? [];
    if (empty($outputComponents)) {
        return [
            'success' => false,
            'message' => 'No outputComponents found in asset data'
        ];
    }
    
    $componentId = $outputComponents[0]['componentId'] ?? '';
    $revision = $outputComponents[0]['revision'] ?? '';
    
    if (empty($componentId) || empty($revision)) {
        return [
            'success' => false,
            'message' => 'Missing componentId or revision in outputComponents'
        ];
    }
    
    // Construct the download URL
    $url = "https://platform-cs-edge-va6.adobe.io/composite/component/id/" . $repoAssetId 
         . "?component_id=" . $componentId 
         . "&revision=" . $revision;
    error_log("ZZZ Download URL: " . $url . "\n");
    
    // Read auth token from file
    $authToken = @file_get_contents('authtoken.txt');
    if ($authToken !== false) {
        $authToken = trim($authToken);
    } else {
        $authToken = '';
    }
    
    // Prepare headers
    $headers = [
        'sec-ch-ua-platform: "macOS"',
        'Authorization: Bearer ' . $authToken,
        'sec-ch-ua: "Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'X-Api-Key: clio-playground-web',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
        'DNT: 1',
        'Client-Agent: v=2; app=Firefly/2d9b146; os=na; device=na; surface=na',
        'Accept: */*',
        'Origin: https://firefly.adobe.com',
        'Sec-Fetch-Site: cross-site',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Dest: empty',
        'Referer: https://firefly.adobe.com/'
    ];
    
    // Download the file using cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    error_log("ZZZ Download result - HTTP code: " . $httpCode . ", Data length: " . (is_string($imageData) ? strlen($imageData) : 'false') . ", cURL error: " . $curlError);
    
    if ($imageData === false || $httpCode !== 200) {
        error_log("ZZZ Download failed - HTTP code: " . $httpCode . ", Error: " . $curlError);
        return [
            'success' => false,
            'message' => 'Failed to download image. HTTP code: ' . $httpCode . ', Error: ' . $curlError,
            'url' => $url
        ];
    }

    error_log("ZZZ Download successful - received " . strlen($imageData) . " bytes");
    
    // Generate unique filename
    $timestamp = microtime(true);
    $filename = $timestamp . '__' . $assetId . '.png';
    $filepath = $outputDir . '/' . $filename;
    
    // Save the file
    $result = file_put_contents($filepath, $imageData);
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Failed to save image to file',
            'filepath' => $filepath
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Image downloaded successfully',
        'filename' => $filename,
        'filepath' => $filepath,
        'url' => $url,
        'filesize' => strlen($imageData)
    ];
}

// Handle AJAX requests
if (isset($_POST['action']) && isset($_POST['filename'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $filename = $_POST['filename'];
    
    if ($action === 'imageClick') {
        $result = image_click($filename);
        echo json_encode($result);
    }
    
    exit;
}

// List of image filenames - dynamically populated from directory
$imageDir = 'firefly_png1';
$images = [];

if (is_dir($imageDir)) {
    $files = scandir($imageDir);
    foreach ($files as $file) {
        // Skip . and .. and only include actual files
        if ($file !== '.' && $file !== '..' && is_file($imageDir . '/' . $file)) {
            $images[] = $file;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Gallery</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 10px;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(256px, 1fr));
            gap: 10px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .image-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .image-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .image-card img {
            max-width: 100%;
            width: auto;
            height: auto;
            display: block;
        }
        
        .image-card .filename {
            display: none;
        }
        
        .image-card.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            display: none;
            z-index: 1000;
        }
        
        .status.show {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <h1>Image Gallery</h1>
    
    <div class="gallery">
        <?php foreach ($images as $image): ?>
            <div class="image-card" data-filename="<?php echo htmlspecialchars($image); ?>">
                <img src="firefly_png1/<?php echo htmlspecialchars($image); ?>" 
                     alt="<?php echo htmlspecialchars($image); ?>"
                     loading="lazy">
                <div class="filename"><?php /* echo htmlspecialchars($image); */?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="status" id="status"></div>
    
    <script>
        // Handle image card clicks
        document.querySelectorAll('.image-card').forEach(card => {
            card.addEventListener('click', function() {
                const filename = this.getAttribute('data-filename');
                
                // Add loading state
                this.classList.add('loading');
                
                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=imageClick&filename=${encodeURIComponent(filename)}`
                })
                .then(response => response.json())
                .then(data => {
                    // Remove loading state
                    this.classList.remove('loading');
                    
                    // Show status message
                    showStatus(data.message || 'Request completed');
                    
                    console.log('Response:', data);
                })
                .catch(error => {
                    // Remove loading state
                    this.classList.remove('loading');
                    
                    // Show error message
                    showStatus('Error: ' + error.message);
                    
                    console.error('Error:', error);
                });
            });
        });
        
        // Show status message
        function showStatus(message) {
            const statusEl = document.getElementById('status');
            statusEl.textContent = message;
            statusEl.classList.add('show');
            
            setTimeout(() => {
                statusEl.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
