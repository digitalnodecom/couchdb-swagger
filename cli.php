<?php

// List of pages that we will scrape
$website = [
    'server' => [
        'https://docs.couchdb.org/en/stable/api/server/common.html'
    ],
    'database' => [
        'https://docs.couchdb.org/en/stable/api/database/common.html',
        'https://docs.couchdb.org/en/stable/api/database/find.html',
        'https://docs.couchdb.org/en/stable/api/database/shard.html',
        'https://docs.couchdb.org/en/stable/api/database/changes.html',
        'https://docs.couchdb.org/en/stable/api/database/compact.html',
        'https://docs.couchdb.org/en/stable/api/database/security.html',
        'https://docs.couchdb.org/en/stable/api/database/misc.html'
    ],
    'document' => [
        'https://docs.couchdb.org/en/stable/api/document/common.html',
        'https://docs.couchdb.org/en/stable/api/document/attachments.html'
    ],
    'design-document' => [
        'https://docs.couchdb.org/en/stable/api/ddoc/common.html',
        'https://docs.couchdb.org/en/stable/api/ddoc/views.html',
        'https://docs.couchdb.org/en/stable/api/ddoc/search.html',
        'https://docs.couchdb.org/en/stable/api/ddoc/render.html',
        'https://docs.couchdb.org/en/stable/api/ddoc/rewrites.html'
    ],
    'partitioned-database' => [
        'https://docs.couchdb.org/en/stable/api/partitioned-dbs.html'
    ]
];

// Initialize Swagger array
$swagger = [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'CouchDB API',
        'version' => '3.3.0'
    ],
    'servers' => [
        ['url' => 'http://localhost:5984']
    ],
    'paths' => [],
    'components' => [
        'schemas' => new stdClass(),
        'responses' => new stdClass(),
        'parameters' => new stdClass(),
        'examples' => new stdClass(),
        'requestBodies' => new stdClass(),
        'headers' => new stdClass(),
        'securitySchemes' => new stdClass(),
        'links' => new stdClass(),
        'callbacks' => new stdClass()
    ]
];

// Process each documentation url
foreach($website as $tag => $urls) {
    foreach($urls as $url) {
        $html = file_get_contents($url);
        $swagger = scrape($html, $html, $tag, $swagger);
    }
}

// Output the Swagger JSON
header('Content-Type: application/json');
echo json_encode($swagger, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

function scrape($html, $xpath, $tag, $swagger) {

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $sections = $xpath->query('//section');

    foreach ($sections as $section) {

        // Detect type and path
        $apicalls = $xpath->query( './/dt[@class="sig sig-object http"]', $section );

        if ($apicalls->length > 0) {

            foreach ($apicalls as $apicall) {

                $methodName = strtolower($xpath->query('.//span[@class="sig-name descname"][1]/span[@class="pre"]', $apicall)->item(0)->textContent);
                $path = $xpath->query('.//span[@class="sig-name descname"][2]/span[@class="pre"]', $apicall)->item(0)->textContent;

                if($methodName == 'copy') {
                    continue;
                }

                if($methodName == 'any') {
                    $methodName = 'post';
                }

                if (!isset($swagger['paths'][$path])) {
                    $swagger['paths'][$path] = [];
                }

                $swagger['paths'][$path][$methodName] = [
                    'summary' => '',
                    'tags' => [
                        $tag
                    ],
                    'description' => '',
                    'parameters' => [],
                    'responses' => []
                ];

                // Description
                $description = $xpath->query('.//dd/p', $section)->item(0);
                if ($description) {
                    $swagger['paths'][$path][$methodName]['description'] = trim($description->textContent);
                }

                // Query Parameters
                $queryParamsSisterColumn = $xpath->query('.//dt[.//text()[contains(., "Query")]]', $section);
                if( $queryParamsSisterColumn->length > 0 ) {
                    $queryParamsColumn = $queryParamsSisterColumn->item(0)->nextSibling->nextSibling;
                    $queryParameters = $xpath->query('.//ul[@class="simple"]/li/p[1]/strong[1]', $queryParamsColumn); // Figure out how to get available string values for example GET /_db_updates (normal, longpoll etc.)

                    foreach ($queryParameters as $parameter) {
                        $name = trim($parameter->textContent);
                        if (isset($parameter->nextSibling->nextSibling)) {
                            $type = trim($parameter->nextSibling->nextSibling->textContent);
                        }

                        $description = $parameter->parentNode->nodeValue ?? null;

                        if($type == 'json') {
                            $type = 'object';
                        }
                        if($type == 'now') {
                            $type = 'object';
                        }
                        if($type == 'json-array') {
                            $type = 'array';
                            $schema_items = [
                                'type' => 'object'
                            ];
                        }
                        if($type == 'array') {
                            $schema_items = [
                                'type' => 'string'
                            ];
                        }
                        if($type == 'string/object') {
                            $type = 'object';
                        }

                        $setParameter = [
                            'name' => $name,
                            'in' => 'query',
                            'description' => $description,
                            'schema' => [
                                'type' => $type
                            ]
                        ];

                        if ($type == 'array') {
                            $setParameter['schema']['items'] = $schema_items;
                        }


                        $swagger['paths'][$path][$methodName]['parameters'][] = $setParameter;

                    }
                }


                // Path Parameters
                if(str_contains($path, "{")) {
                    preg_match_all('/\{([^}]+)\}/', $path, $matches);
                    $pathParams = $matches[1];
                    foreach($pathParams as $param) {
                        $setParameter = [
                            'name' => $param,
                            'in' => 'path',
                            'required' => true,
                            'schema' => [
                                'type' => 'string'
                            ]
                        ];
                        $swagger['paths'][$path][$methodName]['parameters'][] = $setParameter;
                    }
                }

                $responses = $xpath->query('.//dt[contains(text(), "Status Codes")]/following-sibling::dd[1]/ul/li', $section);
                foreach ($responses as $response) {
                    $status = trim($response->getElementsByTagName('span')->item(0)->textContent);
                    $status = intval(explode(' ', $status)[0]);
                    $description = trim($response->textContent);
                    $swagger['paths'][$path][$methodName]['responses'][$status] = ['description' => $description];
                }

                if($responses->length <= 0) {
                    $swagger['paths'][$path][$methodName]['responses']['default'] = ['description' => 'No information about this response. Give it a go!'];
                }

            }
        }
    }

    return $swagger;

}
