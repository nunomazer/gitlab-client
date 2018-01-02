<?php

namespace Torzer\GitlabClient;

/**
 * This class works as a simple client for Gitlab API.
 *
 * @author nunomazer <mazer@torzer.com>
 */
class Gitlab {

    /**
     * Singleton instance
     *
     * @var \Torzer\GitlabClient\Gitlab
     */
    protected static $instance = null;

    /**
     * Base url/uri to Gitlab API
     *
     * @var string
     */
    protected $baseurl = null;

    /**
     * Your client token to access Gitlab API
     * @var string
     */
    protected $token = null;

    /**
     * Guzzle Http Client connection
     *
     * @var \GuzzleHttp\Client
     */
    protected $http = null;

    /**
     * Global headers to send with requests
     * @var array
     */
    protected $headers = null;

    /**
     * Initialize the gloabl vars
     *
     * @param string $token Your Gitlab API token
     * @param string $baseUrl Gitlab base API URI
     */
    protected function __construct($token, $baseUrl= 'https://gitlab.com/api/v4/') {
        $this->baseurl = $baseUrl;
        $this->token = $token;

        $this->headers = [
            'PRIVATE-TOKEN' => $this->token,
        ];

        $this->http = new \GuzzleHttp\Client([
            'base_uri' => $this->baseurl,
            'headers' => $this->headers,
        ]);

    }

    protected function __clone() {
        //Me not like clones! Me smash clones!
    }

    /**
     * Returns a Gitlab client connection instance
     *
     * @param string $token Your Gitlab API token
     * @param string $baseUrl Gitlab base API URI
     * @return \Torzer\GitlabClient\Gitlab Client object connected to Gitlab
     */
    public static function client($token, $baseUrl= 'https://gitlab.com/api/v4/') {
        if (!isset(static::$instance)) {
            static::$instance = new static($token, $baseUrl);
        }
        return static::$instance;
    }

    /**
     * Returns the base URL to Gitlab API
     *
     * @return string
     */
    public function getBaseUrl() {
        return $this->baseurl;
    }

    protected function projectsSegment($project_id) {
        return 'projects/'.$project_id.'/';
    }

    protected function groupsSegment($group_id) {
        return 'groups/'.$group_id.'/';
    }

    protected function issuesSegment() {
        return 'issues';
    }

    protected function repositorySegment() {
        return 'repository';
    }

    protected function mrSegment($id = null) {
        $segment = 'merge_requests';
        $segment.= ($id) ? '/' . $id . '/' : '';
        return $segment;
    }

    protected function tagSegment($name = null) {
        $segment = $this->repositorySegment();
        $segment.= '/tags';
        $segment.= ($name) ? '/' . $name . '/' : '';
        return $segment;
    }

    protected function membersSegment() {
        return 'members';
    }

    protected function milestonesSegment() {
        return 'milestones';
    }


    /**
     * Return one issue by project and issua id
     *
     * @param int $project_id
     * @param int $issue_id
     * @return \stdClass
     */
    public function getIssue($project_id, $issue_id) {
        $uri = $this->projectsSegment($project_id) .
                $this->issuesSegment() .
                '?iids='.$issue_id;

        return json_decode($this->http->get($uri)->getBody()->getContents())[0];
    }


    /**
     * Return one project by id
     *
     * @param int $project_id
     * @return \stdClass
     */
    public function getProject($project_id) {
        $uri = $this->projectsSegment($project_id);

        return json_decode($this->http->get($uri)->getBody()->getContents());
    }

    /**
     * Return an array with the group memebers
     *
     * @param int $group_id
     * @return array
     */
    public function getGroupMembers($group_id) {
        $uri = $this->groupsSegment($group_id) .
                $this->membersSegment();

        $members = json_decode($this->http->get($uri)->getBody()->getContents());

        return $members;
    }

    /**
     * Return an array with the project memebers
     *
     * @param int $project_id
     * @return array
     */
    public function getProjectMembers($project_id) {
        $uri = $this->projectsSegment($project_id) .
                $this->membersSegment();

        $project = $this->getProject($project_id);

        $members = array_merge(
                $this->getGroupMembers($project->namespace->id),
                json_decode($this->http->get($uri)->getBody()->getContents())
            );

        return $members;
    }

    /**
     * Return project milestones
     *
     * @param int $project_id
     * @return array
     */
    public function getProjectMilestones($project_id, $active = true, $closed = false) {
        $uri = $this->projectsSegment($project_id) .
                $this->milestonesSegment() . '?dumb';

        $uri .= ($active) ? '&state=active' : '';
        $uri .= ($closed) ? '&state=closed' : '';

        return json_decode($this->http->get($uri)->getBody()->getContents());
    }

    /**
     * Ceates a new Merge Request
     *
     * @param int $project_id
     * @param String $source Source branch name
     * @param String $target Target branch name
     * @return \stdClass Merge request object
     */
    public function createMR($project_id, $source, $target, $title,
            $description = null,
            $assignee_id = null, $milestone_id = null) {

        $uri = $this->projectsSegment($project_id) .
                $this->mrSegment() .
                '?source_branch='. $source .
                '&target_branch='. $target .
                '&title='. $title .
                '&description='.$description .
                '&assignee_id='.$assignee_id .
                '&milestone_id='.$milestone_id;

        return json_decode($this->http->post($uri)->getBody()->getContents());
    }

    /**
     * Accept a Merge Request
     *
     * @param int $project_id
     * @return \stdClass Merge request object
     */
    public function acceptMR($project_id, $mr_id, $message = null, $removeSourceBranch = false) {

        $uri = $this->projectsSegment($project_id) .
                $this->mrSegment($mr_id) . 'merge/?dumb' .
                '&merge_commit_message='. $message .
                '&should_remove_source_branch='. $removeSourceBranch;

        return json_decode($this->http->put($uri)->getBody()->getContents());
    }

    /**
     * Get list of commits for a Merge Request
     *
     * @param int $project_id
     * @return \stdClass Merge request object
     */
    public function getMRCommits($project_id, $mr_id) {

        $uri = $this->projectsSegment($project_id) .
                $this->mrSegment($mr_id) . 'commits';

        return json_decode($this->http->get($uri)->getBody()->getContents());
    }

    /**
     * Get list of ISSUES closed in this Merge Request
     *
     * @param int $project_id
     * @return \stdClass Merge request object
     */
    public function getMRIssues($project_id, $mr_id) {

        $uri = $this->projectsSegment($project_id) .
                $this->mrSegment($mr_id) . 'closes_issues';

        return json_decode($this->http->get($uri)->getBody()->getContents());
    }

    /**
     * Get list of CHANGES in this Merge Request
     *
     * @param int $project_id
     * @return \stdClass Merge request object
     */
    public function getMRChanges($project_id, $mr_id) {

        $uri = $this->projectsSegment($project_id) .
                $this->mrSegment($mr_id) . 'changes';

        return json_decode($this->http->get($uri)->getBody()->getContents());
    }

    /**
     * Get Merge Request
     *
     * @param int $project_id
     * @return \stdClass Merge request object
     */
    public function getMR($project_id, $mr_id) {

        $uri = $this->projectsSegment($project_id) .
                $this->mrSegment($mr_id);

        return json_decode($this->http->get($uri)->getBody()->getContents());
    }

    /**
     * Create a new tag / realease in project
     *
     * @param int $project_id
     * @return \stdClass Merge request object
     */
    public function createTag($project_id, $tag_name, $ref, $message = null, $release_description = null) {

        $uri = $this->projectsSegment($project_id) .
                $this->tagSegment() .
                '?tag_name=' . $tag_name .
                '&ref=' . $ref .
                '&message=' . $message .
                '&release_description=' . $release_description;

        return json_decode($this->http->post($uri)->getBody()->getContents());
    }


    /**
     * Get tags / realeases in project
     *
     * @param int $project_id
     * @param string $order_by - updated or name
     * @param string $sort - asc or desc
     * @return \stdClass Merge request object
     */
    public function getTags($project_id, $order_by = 'updated', $sort = 'desc') {

        $uri = $this->projectsSegment($project_id) .
                $this->tagSegment() .
                '?order_by=' . $order_by .
                '&sort=' . $sort;

        return json_decode($this->http->get($uri)->getBody()->getContents());
    }

}
