<?php
/**
 * Changelog Generator
 *
 * @author    David Spoerri <ds@hayloft-it.ch>
 * @link      https://github.com/hayloft/changelog-generator
 * @copyright Copyright (c) 2015 Hayloft-IT GmbH (http://www.hayloft-it.ch)
 */

$packageNames = [
    '%^ox/ox-.*%',
];

$appRoot = getcwd();

$logFilePath = $appRoot . '/CHANGELOG.md';

$packagePath = $appRoot  . '/module/';
$composerFile = 'composer.lock';
$composerPath = $appRoot . '/' . $composerFile;

$getPackagePath = function($package) {
    return str_replace(' ', '', ucwords(str_replace(['ox/', '-'], ['', ' '], $package)));
};

$getVcsUrl = function($package, $commit) {
    $project = explode('/', $package)[0];
    $repository = substr($package, strpos($package, '/') + 1);
    return 'http://GITLAB_URL/' . $project . '/' .$repository . '/commit/' . $commit;
};

$replaceTrackerLinks = function($message) {
    return preg_replace('/(#(\d+))/', '[\1](http://REDMINE_URL/issues/\2)', $message);
};
