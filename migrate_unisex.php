<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$db = getDB();

try {
    // 1. Update existing styles to be more inclusive or descriptive
    $db->exec("UPDATE styles SET description = 'Elegant Kente ensemble for prestigious occasions, available for men and women' WHERE name = 'Kente Gown'");
    
    // 2. Add new male/unisex styles if they don't exist
    $styles = [
        [
            'name' => 'Senator Suit',
            'desc' => 'Sharp, modern tailored Senator suit with signature embroidery for the distinguished man.',
            'price' => 550.00,
            'img' => 'assets/images/styles/senator_suit.png'
        ],
        [
            'name' => 'Premium Kaftan',
            'desc' => 'Luxurious, minimalist African Kaftan set. Perfect balance of tradition and modern style.',
            'price' => 480.00,
            'img' => 'assets/images/styles/kente_gown.png' // Placeholder until more specific image is added
        ],
        [
            'name' => 'Unisex Tracksuit',
            'desc' => 'Contemporary African-print infused leisure wear for both men and women.',
            'price' => 250.00,
            'img' => 'assets/images/styles/ankara_jumpsuit.png' // Placeholder
        ]
    ];

    foreach ($styles as $s) {
        $check = $db->prepare("SELECT id FROM styles WHERE name = ?");
        $check->execute([$s['name']]);
        if (!$check->fetch()) {
            $stmt = $db->prepare("INSERT INTO styles (name, description, base_price, image_path, is_active) VALUES (?, ?, ?, ?, TRUE)");
            $stmt->execute([$s['name'], $s['desc'], $s['price'], $s['img']]);
        }
    }

    echo "<h2>Success!</h2><p>Database updated with unisex fashion styles.</p>";
    echo "<a href='index.php'>Go to Homepage</a>";

} catch (PDOException $e) {
    echo "<h2>Error</h2>" . $e->getMessage();
}
?>
