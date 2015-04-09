<?php
/**
 * OfficeX: OX (http://ox.hayloft-it.ch/)
 *
 * @author    David Spoerri <ds@hayloft-it.ch>
 * @link      https://github.com/hayloft/ox
 * @copyright Copyright (c) 2014 Hayloft-IT GmbH (http://www.hayloft-it.ch)
 */

require_once __DIR__ . '/config.php';

if (!isset($argv[1])) {
    die('Please provide a branch, tag or commit id, from which the changelog should be generated.');
}

$oldCommit = get_commit_id($argv[1], '.');
if (null == $oldCommit) {
    die('Unable to find old commit ID!');
}

if (isset($argv[2])) {
    $newComposerContent = get_composer_content($argv[2],$composerFile);
    $version = $argv[2];
} else {
    $version = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
    echo 'No new commit id provided. Using current status of composer file.' . PHP_EOL;
    $newComposerContent = json_decode(file_get_contents($composerPath));
}

$oldComposerContent = get_composer_content($oldCommit, $composerFile);

$oldPackages = get_package_list($oldComposerContent, $packageNames);
$currentPackages = get_package_list($newComposerContent, $packageNames);
$output = '';

$output .= '##  ' . $version . ' (' . date('Y-m-d') . ')' . PHP_EOL;

foreach ($currentPackages as $package) {
    $toCommit = $package->source->reference;

    if (!isset($oldPackages[$package->name])) {
        $fromCommit = null;
    } else {

        $oldPackage = $oldPackages[$package->name];
        $fromCommit = $oldPackage->source->reference;
        if ($fromCommit == $toCommit) {
            // no changes made
            continue;
        }
    }

    $module = $getPackagePath($package->name);
    $moduleDirectory = $packagePath . $module;

    if (!is_dir($moduleDirectory)) {
        echo 'Directory "' . $moduleDirectory . '" for ' . $package->name . ' not found!' . PHP_EOL;
        continue;
    }

    $changes = get_changes($moduleDirectory, $toCommit, $fromCommit);

    if (count($changes) >= 1) {
        $output .= '### ' . $getPackagePath($package->name) . PHP_EOL;

        foreach ($changes as $id => $message) {
            $message = $replaceTrackerLinks($message);
            $message .= ' ([diff](' . $getVcsUrl($package->name, $id) . '))';

            $output .= '- ' . $message . PHP_EOL;
        }
    }
}

add_log_file_contents($logFilePath, $output, $logFileOffset, 1);

/**
 * Gets a list from composer content
 *
 * @param stdClass|null $composerContent
 * @param array $packageRegexes
 * @return array
 */
function get_package_list($composerContent, $packageRegexes) {
    $packages   = [];
    if (null == $composerContent) {
        echo 'Invalid composer content. Correct commit Id(s) provided?';
        return $packages;
    }
    if (!isset($composerContent->packages)) {
        echo 'No packages were found in the composer file.' . PHP_EOL;
        return $packages;
    }
    foreach ($composerContent->packages as $package) {
        $logPackage = false;
        foreach ($packageRegexes as $packageRegex) {
            if (preg_match($packageRegex, $package->name)) {
                $logPackage = true;
                break;
            }
        }

        if (!$logPackage) {
            continue;
        }

        $packages[$package->name] = $package;
    }

    return $packages;
}

/**
 * Gets a list of changes to a repository between two commits.
 *
 * @param string      $path
 * @param string      $to   up to which commit to get changes
 * @param null|string $from from which commit on to get changes. Can be null to get all changes up to the given commit.
 * @return array
 */
function get_changes($path, $to, $from = null) {
    $changes = [];
    $currentPath = getcwd();
    chdir($path);

    if ($from == null) {
        $shellOutput = shell_exec('git log --pretty=oneline ' . $to);
    } else {
        $shellOutput = shell_exec('git log --pretty=oneline ' . $from . '...' . $to);
    }

    $lines = preg_split('/\R/', trim($shellOutput));
    foreach ($lines as $line) {
        if (null != $line) {
            $changes[substr($line, 0, 40)] = trim(substr($line, 40, strlen($line)));
        } else {
            echo 'Could not get changes of repository ' . $path . PHP_EOL;
        }
    }

    chdir($currentPath);

    return $changes;
}

/**
 * Adds text at given point into the changelog file
 *
 * @param string $logfilePath
 * @param string $content
 * @param int $offset amount of characters after which to add the text to the file
 * @param int $newLines amount of newlines after the aded text
 */
function add_log_file_contents($logfilePath, $content, $offset, $newLines = 0) {
    // one newline is added after a change line
    $newLines -= 1;
    for ($i = 0; $i <= $newLines; $i++) {
        $content .= PHP_EOL;
    }

    $existingLog = file_get_contents($logfilePath);

    $newLog = substr_replace($existingLog, $content, $offset, 0);
    file_put_contents($logfilePath, $newLog);
}

/**
 * Gets composer content at a certain commit
 *
 * @param $commit
 * @param $filename
 * @return mixed
 */
function get_composer_content($commit, $filename) {

    return json_decode(shell_exec('git show ' . $commit . ':' . $filename));
}

/**
 * Gets commit id for a revision (e.g. branch or tag). Also recognises commit ids.
 *
 * @param $revision
 * @param $repositoryPath
 * @return string
 */
function get_commit_id($revision, $repositoryPath) {
    if (strlen($revision) == 40) {
        return $revision;
    }

    $currentPath = getcwd();
    chdir($repositoryPath);
    $shellOutput = shell_exec('git show-ref ' . $revision);
    chdir($currentPath);

    return substr($shellOutput, 0, 40);
}
