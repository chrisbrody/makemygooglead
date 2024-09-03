<?php
/**
 * Buymyad
 *
 * @package       BUYMYAD
 * @author        Chris Brody
 * @license       gplv2
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   Buymyad
 * Plugin URI:    https://blog.organicleadgrowth.com/
 * Description:   testing creating free wordpress plugin
 * Version:       1.0.0
 * Author:        Chris Brody
 * Author URI:    https://your-author-domain.com
 * Text Domain:   buymyad
 * Domain Path:   /languages
 * License:       GPLv2
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

// Load Composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(plugin_dir_path(__FILE__));
$dotenv->load();

require_once plugin_dir_path(__FILE__) . 'reddit-api.php';

putenv("OPENAI_API_KEY=" . $_ENV['OPENAI_API_KEY']);

// Debug: Print all environment variables
error_log('Loaded environment variables:');
error_log('OPENAI_API_KEY: ' . (getenv('OPENAI_API_KEY') ? 'Set' : 'Not set'));

class HowDoISellThis {
    private $reddit_client_id;
    private $reddit_client_secret;
    private $reddit_user_agent;
    private $openai_api_key;
    private $max_comments_per_request = 250;
    private $max_tokens_per_request = 4000;

    public function __construct() {
        // Load environment variables
        $this->reddit_client_id = getenv('REDDIT_CLIENT_ID') ?: '';
        $this->reddit_client_secret = getenv('REDDIT_CLIENT_SECRET') ?: '';
        $this->reddit_user_agent = getenv('REDDIT_USER_AGENT') ?: '';
        $this->openai_api_key = getenv('OPENAI_API_KEY') ?: '';

        // Debug: Print loaded values
        error_log('Loaded values in constructor:');
        error_log('reddit_client_id: ' . $this->reddit_client_id);
        error_log('reddit_client_secret: ' . ($this->reddit_client_secret ? 'Set' : 'Not set'));
        error_log('reddit_user_agent: ' . $this->reddit_user_agent);
        error_log('openai_api_key: ' . ($this->openai_api_key ? 'Set' : 'Not set'));

        // Always add the init action
        add_action('init', array($this, 'init'));

        // Check if environment variables are set and add notice if they're missing
        if (empty($this->reddit_client_id) || empty($this->reddit_client_secret) ||
            empty($this->reddit_user_agent) || empty($this->openai_api_key)) {
            add_action('admin_notices', array($this, 'env_variables_missing_notice'));
        }
    }

    public function env_variables_missing_notice() {
        ?>
        <div class="error notice">
            <p><?php _e('HowDoISellThis plugin error: One or more required environment variables are missing. Please check your .env file.', 'buymyad'); ?></p>
        </div>
        <?php
    }

    public function init() {
        // add menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        //display HTML form
        add_shortcode('howdoisellthis_form', array($this, 'render_form'));
        // envoke scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        // get subreddits
        add_action('wp_ajax_get_subreddits', array($this, 'get_subreddits'));
        add_action('wp_ajax_nopriv_get_subreddits', array($this, 'get_subreddits'));
        // search comments
        add_action('wp_ajax_search_comments', array($this, 'search_comments'));
        add_action('wp_ajax_nopriv_search_comments', array($this, 'search_comments'));
        // generate ad copy
        add_action('wp_ajax_generate_ad_copy', array($this, 'generate_ad_copy'));
        add_action('wp_ajax_nopriv_generate_ad_copy', array($this, 'generate_ad_copy'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'HowDoISellThis Settings',
            'HowDoISellThis',
            'manage_options',
            'howdoisellthis-settings',
            array($this, 'render_settings_page'),
            'dashicons-chart-area',
            30
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>HowDoISellThis Settings</h1>
            <p>Reddit API credentials are hardcoded for testing purposes.</p>
        </div>
        <?php
    }

    public function render_form() {
        ob_start();
        ?>
        <div id="howdoisellthis-app">
            <form id="industry-form">
                <div class="form-floating mb-3">
                    <input type="text" id="industry" name="industry" class="form-control" placeholder="Enter your industry">
                    <label for="industry">Enter your industry</label>
                </div>

                <button type="submit" id="find-subreddits" class="btn btn-primary">Search Industry Subreddits</button>
            </form>
            <div id="subreddit-results"></div>
            <div id="searching-comments"></div>
            <div id="complaints-display"></div>
            <div id="adcopy-display"></div>
            <div id="error-message"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_scripts() {
        // Enqueue Bootstrap CSS
        wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . 'vendor/twbs/bootstrap/dist/css/bootstrap.min.css');

        // Enqueue Bootstrap JS
        wp_enqueue_script('bootstrap-js', plugin_dir_url(__FILE__) . 'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.0.2', true);

        // Enqueue your plugin's CSS
        wp_enqueue_style('howdoisellthis-style', plugin_dir_url(__FILE__) . 'css/howdoisellthis.css');

        // Enqueue your plugin's JS
        wp_enqueue_script('howdoisellthis-script', plugin_dir_url(__FILE__) . 'js/howdoisellthis.js', array('jquery'), '1.0', true);

        // Localize script with AJAX URL and nonce
        wp_localize_script('howdoisellthis-script', 'howdoisellthis_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('howdoisellthis-nonce')
        ));
    }

    public function get_subreddits() {
        check_ajax_referer('howdoisellthis-nonce', 'nonce');

        $industry = sanitize_text_field($_POST['industry']);
        error_log('Fetching subreddits for industry: ' . $industry);

        if (empty($industry)) {
            wp_send_json_error(array('message' => 'Industry cannot be empty.'));
            return;
        }

        try {
            $subreddits = $this->call_reddit_api($industry);
            error_log('Subreddits fetched: ' . print_r($subreddits, true));

            if (empty($subreddits)) {
                wp_send_json_error(array('message' => 'No subreddits found for this industry.'));
            } else {
                wp_send_json_success($subreddits);
            }
        } catch (Exception $e) {
            error_log('Error in get_subreddits: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'An error occurred while fetching subreddits.'));
        }
    }

    private function call_reddit_api($industry) {
        error_log("Attempting to call Reddit API for industry: $industry");
        error_log("Current credentials - Client ID: {$this->reddit_client_id}, Client Secret: {$this->reddit_client_secret}, User Agent: {$this->reddit_user_agent}");

        $auth_url = 'https://www.reddit.com/api/v1/access_token';
        $search_url = 'https://oauth.reddit.com/subreddits/search';

        // Get access token
        $ch = curl_init($auth_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . base64_encode($this->reddit_client_id . ":" . $this->reddit_client_secret),
            'User-Agent: ' . $this->reddit_user_agent
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("cURL error: $error");
            throw new Exception("Error getting access token: $error");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        error_log("Access token request HTTP status code: $http_code");

        curl_close($ch);

        error_log("Access token response: $response");

        $auth_data = json_decode($response, true);
        if (!isset($auth_data['access_token'])) {
            error_log('Failed to get access token. Response: ' . print_r($auth_data, true));
            throw new Exception('Failed to get access token');
        }
        $access_token = $auth_data['access_token'];

        // Search for subreddits
        $search_query = urlencode($industry);
        $ch = curl_init("$search_url?q=$search_query");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
            'User-Agent: ' . $this->reddit_user_agent
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error searching subreddits: $error");
        }

        curl_close($ch);

        $search_data = json_decode($response, true);
        if (!isset($search_data['data']['children'])) {
            throw new Exception('Invalid response from Reddit API');
        }

        $subreddits = array();
        foreach ($search_data['data']['children'] as $child) {
            $subreddits[] = $child['data']['display_name_prefixed'];
        }

        return $subreddits;
    }

    public function search_comments() {
        check_ajax_referer('howdoisellthis-nonce', 'nonce');

        $subreddits = isset($_POST['subreddits']) ? $_POST['subreddits'] : array();
        $limit_per_subreddit = 50; // Set the limit to 50 comments per subreddit

        if (empty($subreddits) || count($subreddits) !== 3) {
            wp_send_json_error(array('message' => 'Please select exactly 3 subreddits.'));
            return;
        }

        try {
            $results = $this->get_comments_from_subreddits($subreddits, $limit_per_subreddit);
            if (empty($results)) {
                wp_send_json_error(array('message' => 'No comments found in the selected subreddits.'));
            } else {
                $chatgpt_responses = $this->process_comments_in_batches($results);
                $analyzed_complaints = $this->analyze_chatgpt_responses($chatgpt_responses);
                wp_send_json_success(array(
                    'comments' => $results,
                    'top_complaints' => $analyzed_complaints
                ));
            }
        } catch (Exception $e) {
            error_log('Error in search_comments: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'An error occurred while processing: ' . $e->getMessage()));
        }
    }

    private function process_comments_in_batches($comments) {
        $batches = array_chunk($comments, $this->max_comments_per_request);
        $all_responses = [];

        foreach ($batches as $batch) {
            $batch_response = $this->send_to_chatgpt($batch);
            $all_responses[] = $batch_response;

            // Add a delay to avoid rate limiting
            usleep(1000000); // 1 second delay
        }

        return $all_responses;
    }
    private function send_to_chatgpt($comments) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $prompt = "Analyze the following Reddit comments and identify the top 5 complaints. For each complaint, provide examples of how people are expressing it:\n\n";
        foreach ($comments as $comment) {
            $prompt .= "Subreddit: {$comment['subreddit']}\nComment: {$comment['body']}\n\n";
        }

        $data = array(
            'model' => 'gpt-3.5-turbo-16k',
            'messages' => array(
                array('role' => 'system', 'content' => 'You are an expert at analyzing customer feedback and identifying key complaints. Format your response as JSON with the following structure: {"complaints": [{"topic": "complaint topic", "expressions": ["expression1", "expression2", ...]}, ...]}. Limit your response to the top 5 most significant complaints.'),
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_tokens' => $this->max_tokens_per_request
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openai_api_key
        ));

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error calling ChatGPT API: $error");
        }

        curl_close($ch);

        $response_data = json_decode($response, true);
        if (isset($response_data['choices'][0]['message']['content'])) {
            return json_decode($response_data['choices'][0]['message']['content'], true);
        } else {
            throw new Exception('Invalid response from ChatGPT API');
        }
    }
    private function analyze_chatgpt_responses($responses) {
        $all_complaints = [];

        foreach ($responses as $response) {
            if (isset($response['complaints'])) {
                $all_complaints = array_merge($all_complaints, $response['complaints']);
            } else {
                error_log('Unexpected structure in ChatGPT response');
            }
        }

        // Combine complaints with the same topic
        $combined_complaints = [];
        foreach ($all_complaints as $complaint) {
            $topic = $complaint['topic'];
            if (!isset($combined_complaints[$topic])) {
                $combined_complaints[$topic] = $complaint;
            } else {
                $combined_complaints[$topic]['expressions'] = array_merge(
                    $combined_complaints[$topic]['expressions'],
                    $complaint['expressions']
                );
            }
        }

        // Sort complaints by the number of expressions
        uasort($combined_complaints, function($a, $b) {
            return count($b['expressions']) - count($a['expressions']);
        });

        // Get top 5 complaints
        $top_complaints = array_slice($combined_complaints, 0, 5, true);

        // Limit expressions to top 3 for each complaint
        foreach ($top_complaints as &$complaint) {
            $complaint['expressions'] = array_slice($complaint['expressions'], 0, 3);
        }

        return array_values($top_complaints);
    }
    private function get_comments_from_subreddits($subreddits, $limit_per_subreddit) {
        $results = array();
        $access_token = $this->get_reddit_access_token();

        foreach ($subreddits as $subreddit) {
            error_log("Fetching comments from subreddit: $subreddit");
            $subreddit = ltrim($subreddit, 'r/');
            $after = null;
            $subreddit_comments = 0;

            while ($subreddit_comments < $limit_per_subreddit) {
                $url = "https://oauth.reddit.com/r/{$subreddit}/comments.json?limit=100" . ($after ? "&after=$after" : "");
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization: Bearer ' . $access_token,
                    'User-Agent: ' . $this->reddit_user_agent
                ));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);

                if ($response === false) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    error_log("Error fetching comments from $subreddit: $error");
                    break;
                }

                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                error_log("HTTP status code for $subreddit: $http_code");

                curl_close($ch);

                $comments_data = json_decode($response, true);
                if (!isset($comments_data['data']['children'])) {
                    error_log("Invalid response structure for $subreddit: " . print_r($comments_data, true));
                    break;
                }

                foreach ($comments_data['data']['children'] as $comment) {
                    $results[] = array(
                        'subreddit' => $subreddit,
                        'author' => $comment['data']['author'],
                        'body' => $comment['data']['body'],
                        'link' => "https://www.reddit.com" . $comment['data']['permalink']
                    );

                    $subreddit_comments++;

                    if ($subreddit_comments >= $limit_per_subreddit) {
                        break 2;  // Break both foreach and while loops if we've reached the limit for this subreddit
                    }
                }

                if (isset($comments_data['data']['after']) && $comments_data['data']['after'] !== null) {
                    $after = $comments_data['data']['after'];
                } else {
                    break;  // No more pages to fetch
                }

                // Add a small delay to avoid hitting rate limits
                usleep(100000);  // 100ms delay
            }

            error_log("Fetched $subreddit_comments comments from $subreddit");
        }

        error_log("Total comments fetched across all subreddits: " . count($results));
        return $results;
    }
    private function get_reddit_access_token() {
        $auth_url = 'https://www.reddit.com/api/v1/access_token';

        // Debug: Print Reddit API credentials
        error_log("Reddit API Credentials:");
        error_log("Client ID: " . $this->reddit_client_id);
        error_log("Client Secret: " . $this->reddit_client_secret);
        error_log("User Agent: " . $this->reddit_user_agent);

        $ch = curl_init($auth_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . base64_encode($this->reddit_client_id . ":" . $this->reddit_client_secret),
            'User-Agent: ' . $this->reddit_user_agent
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Error getting access token: $error");
            throw new Exception("Error getting access token: $error");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        error_log("Access token HTTP status code: $http_code");
        error_log("Access token response: " . $response);

        curl_close($ch);

        $auth_data = json_decode($response, true);
        if (!isset($auth_data['access_token'])) {
            error_log('Failed to get access token. Response: ' . print_r($auth_data, true));
            throw new Exception('Failed to get access token');
        }

        return $auth_data['access_token'];
    }
    public function generate_ad_copy() {
        check_ajax_referer('howdoisellthis-nonce', 'nonce');

        $data = json_decode(stripslashes($_POST['data']), true);
        $results = [];

        foreach ($data['selectedComplaints'] as $complaint) {
            $prompt = "Create a Google ad copy addressing the following complaint: \"{$complaint['topic']}\".\n";
            $prompt .= "Here are some expressions of this complaint:\n";
            foreach ($complaint['expressions'] as $expression) {
                $prompt .= "- {$expression}\n";
            }
            $prompt .= "\nProduct/Service Description: {$data['description']}\n";
            $prompt .= "\nCreate a Google ad with:\n1. A catchy headline (max 30 characters)\n2. Two descriptions (max 90 characters each)\n3. A call to action\n\nFormat your response STRICTLY as a JSON object with the following structure:\n{\"headline\": \"Your headline here\", \"description1\": \"Your first description here\", \"description2\": \"Your second description here\", \"callToAction\": \"Your call to action here\"}";

            $api_data = array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are an expert copywriter specializing in Google Ads. Always respond with valid JSON.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens' => 150
            );

            try {
                $response = $this->sendDataForAdCopy($api_data);
                error_log('OpenAI API Response: ' . print_r($response, true));

                if (isset($response['choices'][0]['message']['content'])) {
                    $content = $response['choices'][0]['message']['content'];
                    error_log('Raw content: ' . $content);

                    // Try to extract JSON from the content
                    preg_match('/\{.*\}/s', $content, $matches);
                    $json_string = $matches[0] ?? null;

                    if ($json_string) {
                        $ad_copy = json_decode($json_string, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $results[] = [
                                'complaint' => $complaint['topic'],
                                'ad_copy' => $ad_copy
                            ];
                        } else {
                            error_log('JSON decode error: ' . json_last_error_msg());
                            $results[] = [
                                'complaint' => $complaint['topic'],
                                'ad_copy' => null,
                                'error' => 'Invalid JSON in API response: ' . json_last_error_msg(),
                                'raw_content' => $content
                            ];
                        }
                    } else {
                        error_log('No JSON found in content');
                        $results[] = [
                            'complaint' => $complaint['topic'],
                            'ad_copy' => null,
                            'error' => 'No JSON found in API response',
                            'raw_content' => $content
                        ];
                    }
                } else {
                    error_log('Unexpected API response structure');
                    $results[] = [
                        'complaint' => $complaint['topic'],
                        'ad_copy' => null,
                        'error' => 'Unexpected API response structure'
                    ];
                }
            } catch (Exception $e) {
                error_log('Error in generate_ad_copy: ' . $e->getMessage());
                $results[] = [
                    'complaint' => $complaint['topic'],
                    'ad_copy' => null,
                    'error' => $e->getMessage()
                ];
            }
        }

        wp_send_json_success($results);
    }

    private function sendDataForAdCopy($data) {
        $apiEndpoint = 'https://api.openai.com/v1/chat/completions';
        $apiKey = $this->openai_api_key;

        $ch = curl_init($apiEndpoint);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error sending data to API: $error");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log('OpenAI API HTTP Status Code: ' . $http_code);
        error_log('OpenAI API Raw Response: ' . $response);

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding API response: ' . json_last_error_msg());
        }

        if ($http_code !== 200) {
            throw new Exception('API request failed with status code ' . $http_code . ': ' . ($result['error']['message'] ?? 'Unknown error'));
        }

        return $result;
    }
}

// Initialize the plugin
new HowDoISellThis();
