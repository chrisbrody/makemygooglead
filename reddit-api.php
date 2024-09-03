<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

putenv("REDDIT_CLIENT_ID=" . $_ENV['REDDIT_CLIENT_ID']);
putenv("REDDIT_CLIENT_SECRET=" . $_ENV['REDDIT_CLIENT_SECRET']);
putenv("REDDIT_USER_AGENT=" . $_ENV['REDDIT_USER_AGENT']);

// Debug: Print all environment variables
error_log('Loaded environment variables:');
error_log('REDDIT_CLIENT_ID: ' . getenv('REDDIT_CLIENT_ID'));
error_log('REDDIT_CLIENT_SECRET: ' . (getenv('REDDIT_CLIENT_SECRET') ));
error_log('REDDIT_USER_AGENT: ' . getenv('REDDIT_USER_AGENT'));

class RedditAPI {


    private $client_id;
    private $client_secret;
    private $user_agent;

    public function __construct($client_id, $client_secret, $user_agent) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->user_agent = $user_agent;
    }

    public function get_access_token() {
        $auth_url = 'https://www.reddit.com/api/v1/access_token';
        $ch = curl_init($auth_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . base64_encode($this->client_id . ":" . $this->client_secret),
            'User-Agent: ' . $this->user_agent
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $auth_data = json_decode($response, true);
        return $auth_data['access_token'] ?? null;
    }

    public function search_subreddits($query, $access_token) {
        $search_url = "https://oauth.reddit.com/subreddits/search.json?q=" . urlencode($query);
        $ch = curl_init($search_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
            'User-Agent: ' . $this->user_agent
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $search_data = json_decode($response, true);
        $subreddits = array();
        foreach ($search_data['data']['children'] ?? [] as $child) {
            $subreddits[] = $child['data']['display_name_prefixed'];
        }
        return $subreddits;
    }

    public function get_comments($subreddit, $access_token, $limit = 100) {
        $subreddit = ltrim($subreddit, 'r/');
        $url = "https://oauth.reddit.com/r/{$subreddit}/comments.json?limit=$limit";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
            'User-Agent: ' . $this->user_agent
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $comments_data = json_decode($response, true);
        $comments = array();
        foreach ($comments_data['data']['children'] ?? [] as $comment) {
            $comments[] = array(
                'subreddit' => $subreddit,
                'author' => $comment['data']['author'],
                'body' => $comment['data']['body'],
                'link' => "https://www.reddit.com" . $comment['data']['permalink']
            );
        }
        return $comments;
    }
}