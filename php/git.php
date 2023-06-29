<?php

class Git {

    /**
     * Retrieve the commit ID of the current HEAD.
     * @return string|null commit ID or null on failure
     */
    public static function getCurrentCommitID() {
        $head = file_get_contents(".git/HEAD");

        $matches = [];
        if (preg_match("/ref: (.*)/", $head, $matches)) {
            $head = file_get_contents(".git/{$matches[1]}");
        }

        return $head ? $head : null;
    }
}
