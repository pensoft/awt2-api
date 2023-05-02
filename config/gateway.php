<?php

return [
    "microservices" => [
        "articles" => [
            "host" => env('ARTICLE_SERVICE', "http://article-bo.com/"),
            "ignore_path" => ""
        ],
        "citation-styles" => [
            "host" => env('ARTICLE_SERVICE', "http://article-bo.com/"),
            "ignore_path" => ""
        ],
        "layouts" => [
            "host" => env('ARTICLE_SERVICE', "http://article-bo.com/"),
            "ignore_path" => ""
        ],
        "references" => [
            "host" => env('ARTICLE_SERVICE', "http://article-bo.com/"),
            "ignore_path" => ""
        ],
        "collaborators" => [
            "host" => env('ARTICLE_SERVICE', "http://article-bo.com/"),
            "ignore_path" => ""
        ],
        "users" => [
            "host" => env('AUTH_SERVICE'),
            "ignore_path" => ""
        ],
        "event-dispatcher" => [
            "host" => env('EVENT_DISPATCHER_SERVICE'),
            "ignore_path" => "/event-dispatcher"
        ],
        "article-storage" => [
            "host" => env('ARTICLE_STORAGE_SERVICE'),
            "ignore_path" => "/article-storage"
        ],
        "cdn" => [
            "host" => env('CDN_SERVICE'),
            "ignore_path" => "/cdn"
        ]
    ]
];
