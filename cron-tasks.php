<?php

/**
 * Mise à jour automatique des sources d'informations dans Majestic Start
 * @copyright 2024 Quentin Pugeat
 * @license MIT License
 */

include(__DIR__ . "/init.php");
set_error_handler(function (int $errno, string $errstr, string $errfile = null, int $errline = null, array $errcontext = null) {
    fwrite(STDERR, "$errstr ($errfile:$errline)" . PHP_EOL);
});
set_exception_handler(function ($ex) {
    fwrite(STDERR, $ex->__toString() . PHP_EOL);
});

$db = new Database();

$log_path = __DIR__ . "/cron-tasks.log";
$log = fopen($log_path, "a");

// Mise à jour des informations dans Majestic Start
$categories = $db->select_newscategories();
foreach ($categories as $category_key => $category) {
    $categories[$category_key]["news"] = [];
    $categories[$category_key]["sources"] = $db->select_newssources($category["uuid"]);
    foreach ($categories[$category_key]["sources"] as $source_key => $source) {
        try {
            $rss = NewsAggregator::load_rss($source['uuid'], $source['rss_feed_url']);
            $categories[$category_key]["news"] = array_merge($categories[$category_key]["news"], NewsAggregator::transform($rss->channel->item, $source));
            $db->update_newssource_status($source["uuid"], 1);
        } catch (RuntimeException $ex) {
            if ($source['access_ok'] == 1) {
                if ($log) fwrite($log, date('Y-m-d H:i:s') . " [" . $source['rss_feed_url'] . "] " . str_replace(PHP_EOL, " ", $ex->getMessage()) . PHP_EOL);
                $db->update_newssource_status($source["uuid"], 0);
            }
        }
    }

    NewsAggregator::aggregate($category["uuid"], $categories[$category_key]["news"]);
}

if ($log) fclose($log);
