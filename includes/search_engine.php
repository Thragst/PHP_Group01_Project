<?php
if (!defined('SEARCH_MAX_LENGTH')) {
    define('SEARCH_MAX_LENGTH', 100);
}

function sanitizeSearchQuery($raw): string
{
    if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8')) {
        return '';
    }
    $q = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw);
    $q = preg_replace('/\s+/u', ' ', (string) $q);
    $q = trim((string) $q);
    if (mb_strlen($q, 'UTF-8') > SEARCH_MAX_LENGTH) {
        $q = trim(mb_substr($q, 0, SEARCH_MAX_LENGTH, 'UTF-8'));
    }
    return $q;
}

function searchNormalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', '™' => '', '®' => '',
    ]);
    $text = str_replace(["'", '’', '`'], '', $text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    return trim((string) preg_replace('/\s+/', ' ', (string) $text));
}

function searchStem(string $word): string
{
    if (strlen($word) > 3 && substr($word, -1) === 's'
        && !in_array(substr($word, -2), ['ss', 'us', 'is'], true)) {
        return substr($word, 0, -1);
    }
    return $word;
}

function searchTokenize(string $normalized): array
{
    if ($normalized === '') {
        return [];
    }
    return array_map('searchStem', explode(' ', $normalized));
}

function searchStopwords(): array
{
    static $set = null;
    if ($set === null) {
        $raw = ['a', 'an', 'the', 'for', 'of', 'in', 'on', 'with', 'and', 'or', 'to', 'me', 'my', 'i',
            'we', 'you', 'want', 'need', 'looking', 'look', 'show', 'find', 'search', 'get', 'buy',
            'some', 'any', 'good', 'great', 'nice', 'please', 'shop', 'available', 'product',
            'products', 'item', 'items', 'that', 'is', 'are', 'under', 'below', 'over', 'above',
            'than', 'less', 'more', 'at', 'from', 'by', 'around', 'about', 'like', 'peso', 'pesos',
            'php', 'sport', 'sports', 'gear', 'equipment', 'something', 'anything', 'one', 'pair'];
        $set = array_flip(array_map('searchStem', $raw));
    }
    return $set;
}

function searchSynonyms(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $raw = [
        'sneaker' => ['shoe'], 'kick' => ['shoe'], 'trainer' => ['shoe'], 'footwear' => ['shoe'],
        'boot' => ['cleat'], 'cleat' => ['boot'],
        'hoop' => ['basketball'], 'bball' => ['basketball'], 'nba' => ['basketball'],
        'futbol' => ['soccer'], 'football' => ['soccer'], 'fifa' => ['soccer'],
        'runner' => ['running'], 'jog' => ['running'], 'jogging' => ['running'], 'run' => ['running'],
        'racket' => ['tennis'], 'racquet' => ['tennis'],
        'tshirt' => ['tee'], 'shirt' => ['tee', 'jersey'], 'tee' => ['shirt'],
        'jersey' => ['shirt', 'kit'], 'kit' => ['jersey'],
        'comfy' => ['comfort', 'cushion', 'soft'], 'comfortable' => ['comfort', 'cushion', 'soft'],
        'cushioned' => ['cushion'], 'lightweight' => ['light'], 'grip' => ['traction'],
        'grippy' => ['traction'], 'indoor' => ['turf', 'court'], 'grass' => ['ground', 'artificial'],
        'speed' => ['fast', 'speedform'], 'fast' => ['speed'], 'durable' => ['durability', 'tough'],
    ];
    $map = [];
    foreach ($raw as $k => $alts) {
        $map[searchStem($k)] = array_map('searchStem', $alts);
    }
    return $map;
}

function searchGenderWords(): array
{
    static $map = null;
    if ($map === null) {
        $raw = [
            'men' => ['men', 'man', 'male', 'gentleman'],
            'women' => ['women', 'woman', 'female', 'lady', 'ladie', 'ladies'],
            'kids' => ['kid', 'junior', 'jr', 'youth', 'toddler', 'boy', 'girl', 'child', 'children', 'school'],
        ];
        $map = [];
        foreach ($raw as $group => $words) {
            foreach ($words as $w) {
                $map[searchStem($w)] = $group;
            }
        }
    }
    return $map;
}

function searchTypeWords(): array
{
    static $map = null;
    if ($map === null) {
        $raw = [
            'shoe' => ['footwear', true], 'sneaker' => ['footwear', true], 'kick' => ['footwear', true],
            'footwear' => ['footwear', true], 'trainer' => ['footwear', true],
            'cleat' => ['footwear', false], 'boot' => ['footwear', false],
            'ball' => ['ball', true],
            'apparel' => ['apparel', true], 'clothing' => ['apparel', true], 'clothes' => ['apparel', true],
            'jersey' => ['apparel', false], 'shirt' => ['apparel', false], 'tee' => ['apparel', false],
            'tshirt' => ['apparel', false], 'kit' => ['apparel', false], 'jacket' => ['apparel', false],
            'hoodie' => ['apparel', false], 'short' => ['apparel', false], 'pant' => ['apparel', false],
            'accessory' => ['accessory', true], 'accessorie' => ['accessory', true],
            'sock' => ['accessory', false], 'bag' => ['accessory', false], 'bottle' => ['accessory', false],
            'glove' => ['accessory', false], 'cap' => ['accessory', false], 'band' => ['accessory', false],
        ];
        $map = [];
        foreach ($raw as $w => $v) {
            $map[searchStem($w)] = $v;
        }
    }
    return $map;
}

function searchTypeLabel(string $type): string
{
    $labels = ['footwear' => 'Shoes', 'ball' => 'Balls', 'apparel' => 'Apparel', 'accessory' => 'Accessories'];
    return $labels[$type] ?? ucfirst($type);
}

function searchIntentWords(): array
{
    static $map = null;
    if ($map === null) {
        $raw = [
            'cheap' => 'cheap', 'cheapest' => 'cheap', 'affordable' => 'cheap', 'budget' => 'cheap',
            'inexpensive' => 'cheap',
            'best' => 'best', 'top' => 'best', 'popular' => 'best', 'recommended' => 'best',
            'rated' => 'best', 'favorite' => 'best', 'favourite' => 'best',
            'new' => 'new', 'newest' => 'new', 'latest' => 'new', 'newly' => 'new',
        ];
        $map = [];
        foreach ($raw as $w => $v) {
            $map[searchStem($w)] = $v;
        }
    }
    return $map;
}

function searchCategorySynonyms(): array
{
    return [
        'basketball' => ['hoop', 'hoops', 'bball', 'nba', 'basketball'],
        'football'   => ['soccer', 'futbol', 'fifa', 'football'],
        'running'    => ['run', 'runner', 'jog', 'jogging', 'marathon', 'running'],
        'tennis'     => ['racket', 'racquet', 'tennis'],
    ];
}

function searchBrandAliases(): array
{
    return [
        'under armour' => ['ua', 'underarmour', 'under armor', 'underarmor', 'armour', 'armor'],
    ];
}

function searchDetectProductType(string $nameNorm, string $descNorm): string
{

    if (preg_match('/\b(shoe|shoes|sneaker|sneakers|cleat|cleats|boot|boots|footwear|trainer|trainers|spike|spikes|slide|slides|sandal|sandals)\b/', $nameNorm)) {
        return 'footwear';
    }

    if (preg_match('/\b(jersey|jerseys|kit|kits|tee|tees|shirt|shirts|tank|top|tops|short|shorts|skirt|skort|dress|legging|leggings|jacket|jackets|hoodie|hoodies|pant|pants|tight|tights|sweatshirt|polo|bra|vest|brief|bodysuit)\b/', $nameNorm)) {
        return 'apparel';
    }

    if (preg_match('/\b(sock|socks|bag|bags|bottle|bottles|glove|gloves|cap|caps|band|bands|headband|visor|visors|guard|guards|shin|balaclava|towel|sleeve|sleeves)\b/', $nameNorm)) {
        return 'accessory';
    }

    if (preg_match('/\b(shoe|shoes|sneaker|sneakers|cleat|cleats|footwear|midsole|outsole|insole|sole|upper|lacing|laces|lace|heel|toe)\b/', $descNorm)) {
        return 'footwear';
    }

    if (preg_match('/\b(ball|balls|basketball|football|soccer)\b/', $nameNorm)) {
        return 'ball';
    }
    return '';
}

function searchDetectGender(array $nameWords): array
{
    $map = searchGenderWords();
    $flags = ['men' => false, 'women' => false, 'kids' => false, 'unisex' => false];
    foreach ($nameWords as $w) {
        if (isset($map[$w])) {
            $flags[$map[$w]] = true;
        }
        if ($w === 'unisex') {
            $flags['unisex'] = true;
        }
    }
    return $flags;
}

function searchLoadIndex(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $sql = "SELECT p.product_id, p.category_id, p.name, p.brand, p.description, p.price,
                   p.stock_quantity, p.image_path,
                   c.name AS category_name, c.slug AS category_slug,
                   COALESCE(r.avg_rating, 0) AS avg_rating,
                   COALESCE(r.review_count, 0) AS review_count
            FROM products p
            JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN (
                SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
                FROM product_reviews GROUP BY product_id
            ) r ON r.product_id = p.product_id
            WHERE p.status = 'Active' AND c.status = 'Active'";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $catSyn = searchCategorySynonyms();
    $products = [];
    $brands = [];
    $categories = [];
    $vocabList = [];
    $vocabStem = [];
    $maxPrice = 0.0;
    $maxId = 1;

    foreach ($rows as $r) {
        $nameNorm = searchNormalize((string) $r['name']);
        $nameWords = searchTokenize($nameNorm);
        $brandNorm = searchNormalize((string) ($r['brand'] ?? ''));
        $brandWords = searchTokenize($brandNorm);
        $catNorm = searchNormalize((string) $r['category_name']);
        $catWords = searchTokenize($catNorm);
        $descNorm = searchNormalize(mb_substr((string) ($r['description'] ?? ''), 0, 1500, 'UTF-8'));
        $descWords = array_values(array_unique(searchTokenize($descNorm)));

        $price = (float) $r['price'];
        $maxPrice = max($maxPrice, $price);
        $maxId = max($maxId, (int) $r['product_id']);

        $products[] = [
            'product_id' => (int) $r['product_id'],
            'category_id' => (int) $r['category_id'],
            'name' => (string) $r['name'],
            'brand' => (string) ($r['brand'] ?? ''),
            'price' => $price,
            'image_path' => $r['image_path'],
            'category' => (string) $r['category_name'],
            'stock' => (int) $r['stock_quantity'],
            'rating' => (float) $r['avg_rating'],
            'reviews' => (int) $r['review_count'],
            'nameWords' => array_values(array_unique($nameWords)),
            'nameSet' => array_flip($nameWords),
            'nameStr' => implode(' ', $nameWords),
            'brandKey' => $brandNorm,
            'brandWords' => $brandWords,
            'brandSet' => array_flip($brandWords),
            'catWords' => $catWords,
            'catSet' => array_flip($catWords),
            'descWords' => $descWords,
            'descSet' => array_flip($descWords),
            'type' => searchDetectProductType($nameNorm, $descNorm),
            'gender' => searchDetectGender($nameWords),
        ];

        if ($brandNorm !== '') {
            $brands[$brandNorm] = (string) $r['brand'];
        }
        if (!isset($categories[(int) $r['category_id']])) {
            $words = $catWords;
            $slug = (string) $r['category_slug'];
            if (isset($catSyn[$slug])) {
                $words = array_merge($words, array_map('searchStem', $catSyn[$slug]));
            }
            $categories[(int) $r['category_id']] = [
                'name' => (string) $r['category_name'],
                'words' => array_values(array_unique($words)),
                'nameWords' => $catWords,
            ];
        }
        foreach (array_merge(explode(' ', $nameNorm), explode(' ', $brandNorm), explode(' ', $catNorm)) as $w) {
            if (strlen($w) >= 3 && !ctype_digit($w)) {
                $vocabList[$w] = true;
                $vocabStem[searchStem($w)] = true;
            }
        }
    }

    $cache = [
        'products' => $products,
        'brands' => $brands,
        'categories' => $categories,
        'vocabList' => array_keys($vocabList),
        'vocabStem' => $vocabStem,
        'maxPrice' => $maxPrice > 0 ? $maxPrice : 1.0,
        'maxId' => $maxId,
    ];
    return $cache;
}

function searchAmount(string $number, string $suffix): float
{
    $n = (float) str_replace(',', '', $number);
    if ($suffix !== '') {
        $n *= 1000;
    }
    return $n;
}

function searchExtractPrice(string $raw, array &$parsed): string
{
    $num = '(\d[\d,]*(?:\.\d+)?)\s*(k|thousand|grand)?\b';
    $cur = '(?:₱|php|pesos?)?\s*';

    $between = '/\bbetween\s*' . $cur . $num . '\s*(?:and|to|-)\s*' . $cur . $num . '/iu';
    if (preg_match($between, $raw, $m)) {
        $a = searchAmount($m[1], $m[2] ?? '');
        $b = searchAmount($m[3], $m[4] ?? '');
        $parsed['price_min'] = min($a, $b);
        $parsed['price_max'] = max($a, $b);
        return (string) preg_replace($between, ' ', $raw, 1);
    }

    $max = '/\b(?:under|below|less\s+than|cheaper\s+than|max(?:imum)?|up\s+to|within|not\s+more\s+than|at\s+most)\s*' . $cur . $num . '/iu';
    if (preg_match($max, $raw, $m)) {
        $parsed['price_max'] = searchAmount($m[1], $m[2] ?? '');
        $raw = (string) preg_replace($max, ' ', $raw, 1);
    }

    $min = '/\b(?:over|above|more\s+than|at\s+least|min(?:imum)?|starting\s+(?:at|from)|from)\s*' . $cur . $num . '/iu';
    if (preg_match($min, $raw, $m)) {
        $parsed['price_min'] = searchAmount($m[1], $m[2] ?? '');
        $raw = (string) preg_replace($min, ' ', $raw, 1);
    }

    if ($parsed['price_max'] === null && $parsed['price_min'] === null) {
        $lone = '/(?:₱|\bphp\b)\s*' . $num . '/iu';
        if (preg_match($lone, $raw, $m)) {
            $parsed['price_max'] = searchAmount($m[1], $m[2] ?? '');
            $raw = (string) preg_replace($lone, ' ', $raw, 1);
        }
    }
    return $raw;
}

function searchParseQuery(string $query, array $index): array
{
    $parsed = [
        'price_min' => null, 'price_max' => null,
        'brand' => null,        
        'category_id' => null,
        'gender' => null,       
        'type' => null,         
        'intent' => [],         
        'tokens' => [],         
        'chips' => [],
    ];

    $raw = mb_strtolower($query, 'UTF-8');
    $raw = searchExtractPrice($raw, $parsed);
    $norm = searchNormalize($raw);


    $aliases = searchBrandAliases();
    foreach ($index['brands'] as $key => $display) {
        $candidates = array_merge([$key, str_replace(' ', '', $key)], $aliases[$key] ?? []);
        foreach ($candidates as $alias) {
            $pattern = '/(?<![a-z0-9])' . preg_quote($alias, '/') . 's?(?![a-z0-9])/';
            if (preg_match($pattern, $norm)) {
                $parsed['brand'] = $key;
                $norm = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace($pattern, ' ', $norm, 1)));
                break 2;
            }
        }
    }


    $stop = searchStopwords();
    $genderMap = searchGenderWords();
    $typeMap = searchTypeWords();
    $intentMap = searchIntentWords();
    $content = [];

    foreach (searchTokenize($norm) as $t) {
        if ($t === '' || (strlen($t) === 1 && !ctype_digit($t))) {
            continue;
        }
        if (isset($intentMap[$t])) {
            $parsed['intent'][$intentMap[$t]] = true;
            continue;
        }
        if (isset($stop[$t])) {
            continue;
        }
        if (isset($genderMap[$t])) {
            if ($parsed['gender'] === null) {
                $parsed['gender'] = $genderMap[$t];
            }
            continue;
        }
        if ($parsed['category_id'] === null) {
            $found = null;
            foreach ($index['categories'] as $id => $cat) {
                if (in_array($t, $cat['words'], true)) {
                    $found = $id;
                    break;
                }
                
                if (strlen($t) >= 5 && !ctype_digit($t)) {
                    foreach ($cat['nameWords'] as $cw) {
                        if (strlen($cw) >= 5 && levenshtein($t, $cw) <= 1) {
                            $found = $id;
                            break 2;
                        }
                    }
                }
            }
            if ($found !== null) {
                $parsed['category_id'] = $found;
                continue;
            }
        }
        if (isset($typeMap[$t])) {
            if ($parsed['type'] === null) {
                $parsed['type'] = $typeMap[$t][0];
            }
            if ($typeMap[$t][1]) {
                continue;
            }
        }
        $content[] = $t;
    }


    if ($parsed['brand'] === null) {
        foreach ($content as $i => $t) {
            if (strlen($t) < 4 || ctype_digit($t) || isset($index['vocabStem'][$t])) {
                continue;
            }
            $dist = strlen($t) >= 7 ? 2 : 1;
            foreach ($index['brands'] as $key => $display) {
                foreach (explode(' ', $key) as $bw) {
                    if (strlen($bw) >= 4 && levenshtein($t, searchStem($bw)) <= $dist) {
                        $parsed['brand'] = $key;
                        unset($content[$i]);
                        break 3;
                    }
                }
            }
        }
        $content = array_values($content);
    }

    $hasStructure = $parsed['brand'] !== null || $parsed['category_id'] !== null
        || $parsed['gender'] !== null || $parsed['type'] !== null
        || $parsed['price_min'] !== null || $parsed['price_max'] !== null
        || !empty($parsed['intent']);
    if (empty($content) && !$hasStructure) {
        $content = searchTokenize($norm);
    }
    $parsed['tokens'] = array_values(array_unique($content));


    if ($parsed['brand'] !== null) {
        $parsed['chips'][] = 'Brand: ' . ($index['brands'][$parsed['brand']] ?? $parsed['brand']);
    }
    if ($parsed['category_id'] !== null && isset($index['categories'][$parsed['category_id']])) {
        $parsed['chips'][] = 'Category: ' . $index['categories'][$parsed['category_id']]['name'];
    }
    if ($parsed['type'] !== null) {
        $parsed['chips'][] = 'Type: ' . searchTypeLabel($parsed['type']);
    }
    if ($parsed['gender'] !== null) {
        $parsed['chips'][] = 'For: ' . ['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'][$parsed['gender']];
    }
    if ($parsed['price_min'] !== null && $parsed['price_max'] !== null) {
        $parsed['chips'][] = 'Price: ₱' . number_format($parsed['price_min']) . ' – ₱' . number_format($parsed['price_max']);
    } elseif ($parsed['price_max'] !== null) {
        $parsed['chips'][] = 'Price: under ₱' . number_format($parsed['price_max']);
    } elseif ($parsed['price_min'] !== null) {
        $parsed['chips'][] = 'Price: over ₱' . number_format($parsed['price_min']);
    }
    if (isset($parsed['intent']['cheap'])) {
        $parsed['chips'][] = 'Lower prices first';
    }
    if (isset($parsed['intent']['best'])) {
        $parsed['chips'][] = 'Top rated first';
    }
    if (isset($parsed['intent']['new'])) {
        $parsed['chips'][] = 'Newest first';
    }
    return $parsed;
}


function searchMatchStrength(string $t, array $p): float
{
    $len = strlen($t);
    $isNum = ctype_digit($t);
    $best = 0.0;

    if (isset($p['nameSet'][$t])) {
        return 10.0;
    }
    if (isset($p['brandSet'][$t])) {
        $best = 9.0;
    } elseif (isset($p['catSet'][$t])) {
        $best = 7.0;
    } elseif (isset($p['descSet'][$t])) {
        $best = 2.0;
    }
    if ($isNum || $len < 2) {
        return $best;
    }


    foreach ($p['nameWords'] as $w) {
        if (strncmp($w, $t, $len) === 0) {
            $best = max($best, $len >= 3 ? 7.0 : 5.0);
            break;
        }
    }
    if ($len >= 4 && strpos($p['nameStr'], $t) !== false) {
        $best = max($best, 5.0);
    }
    foreach ($p['brandWords'] as $w) {
        if (strncmp($w, $t, $len) === 0) {
            $best = max($best, 6.0);
            break;
        }
    }
    foreach ($p['catWords'] as $w) {
        if (strncmp($w, $t, $len) === 0) {
            $best = max($best, 5.0);
            break;
        }
    }
    if ($len >= 3 && $best < 1.0) {
        foreach ($p['descWords'] as $w) {
            if (strncmp($w, $t, $len) === 0) {
                $best = 1.0;
                break;
            }
        }
    }

   
    if ($len >= 4 && $best < 4.0) {
        $max = $len >= 7 ? 2 : 1;
        foreach (array_merge($p['nameWords'], $p['brandWords'], $p['catWords']) as $w) {
            if (abs(strlen($w) - $len) <= $max && !ctype_digit($w) && levenshtein($t, $w) <= $max) {
                $best = max($best, 4.0);
                break;
            }
        }
    }
    return $best;
}


function searchTokenScore(string $t, array $p): float
{
    $syn = searchSynonyms();
    $best = searchMatchStrength($t, $p);
    if (isset($syn[$t])) {
        foreach ($syn[$t] as $alt) {
            $best = max($best, 0.7 * searchMatchStrength($alt, $p));
        }
    }
    return $best;
}

function searchGenderAllowed(array $flags, string $want): bool
{
    if ($flags[$want]) {
        return true;
    }
    if ($flags['unisex']) {
        return true;
    }
    foreach (['men', 'women', 'kids'] as $g) {
        if ($g !== $want && $flags[$g]) {
            return false; 
        }
    }
    return true;
}

function searchRun(array $index, array $parsed, array $apply): array
{
    $tokens = $parsed['tokens'];
    $n = count($tokens);
    $need = $n === 0 ? 0 : max(1, (int) ceil($n * 0.6));
    $phrase = $n >= 2 ? implode(' ', $tokens) : '';
    $results = [];

    foreach ($index['products'] as $p) {
    
        if ($apply['brand'] && $parsed['brand'] !== null && $p['brandKey'] !== $parsed['brand']) {
            continue;
        }
        if ($apply['category'] && $parsed['category_id'] !== null && $p['category_id'] !== $parsed['category_id']) {
            continue;
        }
        if ($apply['price']) {
            if ($parsed['price_max'] !== null && $p['price'] > $parsed['price_max']) {
                continue;
            }
            if ($parsed['price_min'] !== null && $p['price'] < $parsed['price_min']) {
                continue;
            }
        }
        if ($apply['gender'] && $parsed['gender'] !== null && !searchGenderAllowed($p['gender'], $parsed['gender'])) {
            continue;
        }
        if ($apply['type'] && $parsed['type'] !== null) {
            if ($p['type'] === '') {
                if (in_array($parsed['type'], ['ball', 'accessory'], true)) {
                    continue;
                }
            } elseif ($p['type'] !== $parsed['type']) {
                continue;
            }
        }

     
        $score = 0.0;
        $matched = 0;
        foreach ($tokens as $t) {
            $s = searchTokenScore($t, $p);
            if ($s > 0) {
                $matched++;
                $score += $s;
            }
        }
        if ($n > 0) {
            if ($matched < $need) {
                continue;
            }
            if ($matched === $n) {
                $score += 15;
            }
            if ($phrase !== '' && strpos($p['nameStr'], $phrase) !== false) {
                $score += 12;
            }
        }

      
        if ($apply['brand'] && $parsed['brand'] !== null) {
            $score += 6;
        }
        if ($apply['category'] && $parsed['category_id'] !== null) {
            $score += 5;
        }
        if ($apply['type'] && $parsed['type'] !== null && $p['type'] === $parsed['type']) {
            $score += 4;
        }
        if ($apply['gender'] && $parsed['gender'] !== null) {
            $score += $p['gender'][$parsed['gender']] ? 4 : ($p['gender']['unisex'] ? 2 : 0);
        }

        
        $score += $p['rating'] * 0.6 + min(log(1 + $p['reviews']), 2.0);
        $score += $p['stock'] > 0 ? 1.5 : -6.0;

      
        if (isset($parsed['intent']['cheap'])) {
            $score += (1 - min(1.0, $p['price'] / $index['maxPrice'])) * 8;
        }
        if (isset($parsed['intent']['best'])) {
            $score += $p['rating'] * 2;
        }
        if (isset($parsed['intent']['new'])) {
            $score += ($p['product_id'] / $index['maxId']) * 6;
        }

        $p['score'] = $score;
        $results[] = $p;
    }

    usort($results, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return $b['product_id'] <=> $a['product_id'];
        }
        return $b['score'] <=> $a['score'];
    });
    return $results;
}

/* ------------------------------------------------------------------ */
/* "Did you mean ...?"                                                 */
/* ------------------------------------------------------------------ */

function searchDidYouMean(string $query, array $index): ?string
{
    $norm = searchNormalize($query);
    if ($norm === '') {
        return null;
    }
    $known = array_merge(
        searchStopwords(), searchSynonyms(), searchGenderWords(), searchTypeWords(), searchIntentWords()
    );
    foreach (searchCategorySynonyms() as $words) {
        foreach ($words as $w) {
            $known[searchStem($w)] = true;
        }
    }
    foreach (searchBrandAliases() as $words) {
        foreach ($words as $w) {
            $known[searchStem($w)] = true;
        }
    }

    foreach (['between', 'within', 'maximum', 'minimum', 'cheaper', 'least', 'most', 'starting', 'thousand', 'grand'] as $w) {
        $known[searchStem($w)] = true;
    }

    $changed = false;
    $out = [];
    foreach (explode(' ', $norm) as $w) {
        $s = searchStem($w);
        if (strlen($w) < 4 || ctype_digit($w) || isset($known[$s]) || isset($index['vocabStem'][$s])) {
            $out[] = $w;
            continue;
        }
        $isPrefix = false;
        foreach ($index['vocabList'] as $v) {
            if (strncmp($v, $w, strlen($w)) === 0) {
                $isPrefix = true;
                break;
            }
        }
        if ($isPrefix) {
            $out[] = $w;
            continue;
        }
        $max = strlen($w) >= 7 ? 2 : 1;
        $bestWord = null;
        $bestDist = $max + 1;
        foreach ($index['vocabList'] as $v) {
            if (abs(strlen($v) - strlen($w)) > $max) {
                continue;
            }
            $d = levenshtein($w, $v);
            if ($d < $bestDist) {
                $bestDist = $d;
                $bestWord = $v;
            }
        }
        if ($bestWord !== null) {
            $out[] = $bestWord;
            $changed = true;
        } else {
            $out[] = $w;
        }
    }
    return $changed ? implode(' ', $out) : null;
}


function smartSearch(PDO $pdo, string $query, array $options = []): array
{
    $query = sanitizeSearchQuery($query);
    $empty = ['query' => $query, 'results' => [], 'total' => 0, 'relaxed' => 0,
              'chips' => [], 'didYouMean' => null, 'categoryCounts' => []];
    if ($query === '') {
        return $empty;
    }

    $index = searchLoadIndex($pdo);
    $parsed = searchParseQuery($query, $index);


    $levels = [
        ['brand' => true,  'category' => true,  'price' => true,  'gender' => true,  'type' => true],
        ['brand' => true,  'category' => true,  'price' => false, 'gender' => true,  'type' => true],
        ['brand' => true,  'category' => true,  'price' => false, 'gender' => false, 'type' => false],
        ['brand' => false, 'category' => false, 'price' => false, 'gender' => false, 'type' => false],
    ];

    $found = [];
    $levelUsed = 0;
    foreach ($levels as $i => $apply) {
        $hasConstraint = false;
        foreach (['brand' => 'brand', 'category' => 'category_id', 'gender' => 'gender', 'type' => 'type'] as $k => $field) {
            if ($apply[$k] && $parsed[$field] !== null) {
                $hasConstraint = true;
            }
        }
        if ($apply['price'] && ($parsed['price_min'] !== null || $parsed['price_max'] !== null)) {
            $hasConstraint = true;
        }

        if (empty($parsed['tokens']) && !$hasConstraint) {
            continue;
        }
        $found = searchRun($index, $parsed, $apply);
        if (!empty($found)) {
            $levelUsed = $i;
            break;
        }
    }

    if ($levelUsed === 1 && !empty($found)) {
        if ($parsed['price_max'] !== null) {
            usort($found, function ($a, $b) { return $a['price'] <=> $b['price']; });
        } elseif ($parsed['price_min'] !== null) {
            usort($found, function ($a, $b) { return $b['price'] <=> $a['price']; });
        }
    }

    $counts = [];
    foreach ($found as $r) {
        $counts[$r['category_id']] = ($counts[$r['category_id']] ?? 0) + 1;
    }
    if (!empty($options['category_id'])) {
        $cid = (int) $options['category_id'];
        $found = array_values(array_filter($found, function ($r) use ($cid) {
            return $r['category_id'] === $cid;
        }));
    }

    $dym = searchDidYouMean($query, $index);

    return [
        'query' => $query,
        'results' => $found,
        'total' => count($found),
        'relaxed' => $levelUsed,
        'chips' => $parsed['chips'],
        'didYouMean' => $dym,
        'categoryCounts' => $counts,
    ];
}

function logSearchQuery(PDO $pdo, string $query, int $resultCount): void
{
    $query = trim(mb_strtolower(sanitizeSearchQuery($query), 'UTF-8'));
    if ($query === '' || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $last = $_SESSION['last_search_logged'] ?? null;
    if (is_array($last) && ($last['q'] ?? '') === $query && (time() - (int) ($last['t'] ?? 0)) < 60) {
        return;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO search_analytics (keyword_searched, results_found) VALUES (?, ?)');
        $stmt->execute([mb_substr($query, 0, 255, 'UTF-8'), max(0, $resultCount)]);
        $_SESSION['last_search_logged'] = ['q' => $query, 't' => time()];
    } catch (Throwable $e) {

    }
}
