<?php
// Load preschools data
$preschools = json_decode(file_get_contents(__DIR__ . '/../docs/preschools.json'), true);

// Create lookup array for faster matching
$preschoolLookup = [];
foreach ($preschools['features'] as $feature) {
    $key = $feature['properties']['city'] . '/' . $feature['properties']['title'];
    $preschoolLookup[$key] = $feature['properties']['id'];
}

// Initialize results array
$results = [];

// Scan all punishment files
$files = glob(__DIR__ . '/../docs/data/punish/*/*.json');
foreach ($files as $file) {
    // Extract city and title from file path
    preg_match('#/punish/([^/]+)/([^/]+)\.json$#', $file, $matches);
    
    // Look up preschool data
    $lookupKey = $matches[1] . '/' . $matches[2];
    if (!isset($preschoolLookup[$lookupKey])) {
        echo "Warning: No match found for {$matches[1]}/{$matches[2]}\n";
        continue;
    }
    // Load punishment data
    $punishData = json_decode(file_get_contents($file), true);

    // Add selected punishment fields
    foreach ($punishData as $punishment) {
        if(!isset($results[$punishment[4]])) {
            $results[$punishment[4]] = [];
        }
        $results[$punishment[4]][] = [
            'date' => $punishment[0],
            'law' => $punishment[3],
            'punishment' => $punishment[5],
            'id' => $preschoolLookup[$lookupKey],
        ];
    }
}

file_put_contents(__DIR__ . '/../docs/punish_all.json', json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "Combined " . count($results) . " preschools with punishment data\n";
