<?php

class Git {

    /**
     * Retrieve the commit ID of the current HEAD.
     * @return string|null commit ID or null on failure
     */
    public static function getCurrentCommitID() {
        $head = @file_get_contents(".git/HEAD");
        if ($head === false) {
            return null;
        }

        $matches = [];
        if (preg_match("/ref: (.*)/", $head, $matches)) {
            $ref = trim($matches[1]);
            $head = @file_get_contents(".git/" . $ref);
            if ($head === false) {
                return null;
            }
        }

        $head = trim($head);
        return $head !== "" ? $head : null;
    }
}
