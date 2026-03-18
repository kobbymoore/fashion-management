<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();

$imageDir = __DIR__ . '/assets/images/';
$stylesDir = $imageDir . 'styles/';
if (!is_dir($stylesDir)) mkdir($stylesDir, 0777, true);

$mappings = [
    'agbada.jpg' => 'Agbada Set',
    'ankara jumpsuit.jpg' => 'Ankara Jumpsuit',
    'bridal gown.jpg' => 'Bridal Gown',
    'casual sundress.jpg' => 'Casual Sundress',
    'corporate skirt suit.jpg' => 'Corporate Skirt Suit',
    'kente gown.jpg' => 'Kente Gown',
    'premium kaftans men.jpg' => 'Premium Kaftan',
    'senator suit.jpg' => 'Senator Suit',
    'unisex tracksuit.jpg' => 'Unisex Tracksuit'
];

echo "Starting Sync...\n";

foreach ($mappings as $file => $styleName) {
    if (file_exists($imageDir . $file)) {
        $newName = str_replace(' ', '_', $file);
        $newPath = 'assets/images/styles/' . $newName;
        
        if (copy($imageDir . $file, __DIR__ . '/' . $newPath)) {
            echo "Matched: $file -> Updating $styleName\n";
            $stmt = $db->prepare("UPDATE styles SET image_path = ? WHERE name LIKE ?");
            // Use LIKE %...% to match even if it's "Premium Kaftan" vs "Premium Kaftans"
            $searchTerm = '%' . $styleName . '%';
            $stmt->execute([$newPath, $searchTerm]);
        } else {
            echo "Failed to move: $file\n";
        }
    } else {
        echo "File not found: $file\n";
    }
}

echo "Sync Complete.\n";
