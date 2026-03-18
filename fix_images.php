<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$db = getDB();

try {
    echo "Updating style image paths...<br>";

    // Update existing styles with correct extensions and recovered files
    $updates = [
        'Kente Gown' => 'assets/images/styles/kente_gown.png',
        'Ankara Jumpsuit' => 'assets/images/styles/ankara_jumpsuit.png',
        'Corporate Skirt Suit' => 'assets/images/styles/kente_gown.png', // Temporary professional fallback
        'Casual Sundress' => 'assets/images/styles/ankara_jumpsuit.png', // Temporary fallback
        'Bridal Gown' => 'assets/images/styles/kente_gown.png', // Temporary fallback
        'Agbada Set' => 'assets/images/styles/senator_suit.png', // Temporary unisex fallback
        'Senator Suit' => 'assets/images/styles/senator_suit.png'
    ];

    foreach ($updates as $name => $path) {
        $stmt = $db->prepare("UPDATE styles SET image_path = ? WHERE name = ?");
        $stmt->execute([$path, $name]);
        echo "Updated $name to $path<br>";
    }

    echo "<h2>Success!</h2><p>All style images have been linked to recovered professional assets.</p>";
    echo "<a href='index.php'>Go to Homepage</a>";

} catch (PDOException $e) {
    echo "<h2>Error</h2>" . $e->getMessage();
}
?>
