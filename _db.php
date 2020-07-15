<?php

defined('SEEMS_LEGIT') or die('nope');

class DB {
    /**
     * @var SQLite3
     */
    private $sql;

    /**
     * DB constructor.
     */
    private function __construct(SQLite3 $sql)
    {
        $this->sql = $sql;
    }

    /**
     * Gets the GitHub Username (as stored in the DB) from the given memberid
     * @param $memberid
     * @return string|null
     */
    public function getGitHubUsername($memberid) {
        $stmt = $this->sql->prepare('SELECT github_username FROM reviewers WHERE memberid = :id');
        $stmt->bindParam('id', $memberid, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if(!$result) {
            die('Failed to get github username');
        }
        $row = $result->fetchArray();
        if(!$row) {
            return null;
        }
        return $row['github_username'];
    }

    public function setGithubUsername($memberid, $username) {
        $stmt = $this->sql->prepare(
            'INSERT INTO reviewers (memberid, github_username)
            VALUES (:memberid, :username)
            ON CONFLICT (memberid) DO
                UPDATE SET github_username = :username'
        );
        if (empty($stmt)) {
            die('Failed to prepare setUsername');
        }
        $stmt->bindParam('memberid', $memberid, SQLITE3_INTEGER);
        $stmt->bindParam('username', $username, SQLITE3_TEXT);
        if (!($stmt->execute())) {
            die('Failed to set username');
        }
    }

    /**
     * Get all projects known to the system.
     *
     * The returned array will have elements:
     * - repo - the GitHub repository name (including the org prefix, e.g. UniversityRadioYork/review_machine)
     * - descr - a text description of the project
     * - languages - a text description of the languages the project is written in
     * @return array
     */
    public function getProjects() {
        $qResult = $this->sql->query('SELECT repo, descr, languages FROM projects');
        if(!$qResult) {
            die('Failed to get projects');
        }
        $result = [];
        while ($row = $qResult->fetchArray(SQLITE3_ASSOC)) {
            $result[] = $row;
        }
        return $result;
    }

    public function getMemberProjects($memberid) {
        $stmt = $this->sql->prepare('SELECT repo FROM reviewer_repos WHERE memberid = :id');
        $stmt->bindParam('id', $memberid, SQLITE3_INTEGER);
        $qResult = $stmt->execute();
        if(!$qResult) {
            die('Failed to get member projects');
        }
        $result = [];
        while ($row = $qResult->fetchArray(SQLITE3_ASSOC)) {
            $result[] = $row['repo'];
        }
        return $result;
    }

    public function updateMemberProjects(int $memberid, array $newMemberProjectRepos) {
        $oldMemberProjects = $this->getMemberProjects($memberid);

        $add = array_diff($newMemberProjectRepos, $oldMemberProjects);
        $remove = array_diff($oldMemberProjects, $newMemberProjectRepos);

        if(!$this->sql->exec('BEGIN;')) {
            die('Failed to begin');
        }

        $addStmt = $this->sql->prepare(
            'INSERT INTO reviewer_repos (memberid, repo)
            VALUES (:memberid, :repo)
            ON CONFLICT (memberid, repo) DO NOTHING'
        );
        if (empty($addStmt)) {
            die('Failed to prepare addStmt');
        }
        foreach ($add as $repo) {
            $addStmt->bindParam('memberid', $memberid, SQLITE3_INTEGER);
            $addStmt->bindParam('repo', $repo, SQLITE3_TEXT);
            if ($addStmt->execute() === FALSE) {
                $this->sql->exec('ROLLBACK;');
                die('Failed to add: ' . $this->sql->lastErrorMsg());
            }
            $addStmt->reset();
            $addStmt->clear();
        }

        $removeStmt = $this->sql->prepare('DELETE FROM reviewer_repos WHERE memberid = :memberid AND repo = :repo');
        if(empty($removeStmt)) {
            die('Failed to prepare removeStmt');
        }
        foreach ($remove as $repo) {
            $removeStmt->bindParam('memberid', $memberid, SQLITE3_INTEGER);
            $removeStmt->bindParam('repo', $repo, SQLITE3_TEXT);
            if ($removeStmt->execute() === FALSE) {
                $this->sql->exec('ROLLBACK;');
                die('Failed to remove');
            }
            $removeStmt->reset();
            $removeStmt->clear();
        }

        if(!$this->sql->exec('COMMIT;')) {
            die('Failed to commit');
        }
    }

    public function getReviewersForRepo($repo) {
        $stmt = $this->sql->prepare('SELECT
       DISTINCT github_username
FROM reviewer_repos
    INNER JOIN reviewers USING (memberid)
WHERE repo = :repo');
        $stmt->bindParam('repo', $repo, SQLITE3_TEXT);
        $qResult = $stmt->execute();
        if(!$qResult) {
            die('Failed to get member projects');
        }
        $result = [];
        while ($row = $qResult->fetchArray(SQLITE3_ASSOC)) {
            $result[] = $row['github_username'];
        }
        return $result;
    }

    public static function getInstance() {
        $db = new SQLite3('review_machine.sqlite3', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);

        if(!($db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS projects (
    repo TEXT PRIMARY KEY,
    descr TESXT,
    languages TEXT    
);
CREATE TABLE IF NOT EXISTS reviewers (
    memberid INTEGER NOT NULL PRIMARY KEY,
    github_username TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS reviewer_repos (
    reviewer_repo_id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    memberid INTEGER NOT NULL,
    repo TEXT NOT NULL REFERENCES projects(repo),
    UNIQUE(memberid, repo)
);
SQL
        ))) {
            die('Something exploded!');
        }

        return new DB($db);
    }
}