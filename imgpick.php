<?php
// imgpick.php is a simple image gallery that allows users to click on images
// to have them copied to a directory.
// Mark Riordan  2026-01-12  with GitHub Copilot assistance

// Handle AJAX requests
if (isset($_POST['action']) && isset($_POST['filename'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $filename = $_POST['filename'];
    
    // Placeholder function - will be filled in later
    if ($action === 'imageClick') {
        // TODO: Implement image click handler
        echo json_encode([
            'success' => true,
            'message' => 'Image click received',
            'filename' => $filename
        ]);
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
