<?php
namespace Payzard;

class PayzardPlugin {

    const BASE_URL = 'https://payzard.com';
    const ACCOUNT_ID_OPTION = 'payzard_account_id';
    const NETWORK_ENABLED_OPTION = 'payzard_network_enabled';
    const ACTIVATION_ERROR_OPTION = 'payzard_activation_error';
    
    public function __construct($basePluginFile) {
        add_action('wp_head', array($this, 'snippet'));
        
        register_activation_hook($basePluginFile, array($this, 'activate'));
        register_deactivation_hook($basePluginFile, array($this, 'deactivate'));
        
        register_uninstall_hook($basePluginFile, array('\Payzard\PayzardPlugin', 'uninstall'));
    }
    
    public function snippet() {
        $isNetworkEnabled = get_site_option(self::NETWORK_ENABLED_OPTION, false);
        if ($isNetworkEnabled) {
            $accountId = get_site_option(self::ACCOUNT_ID_OPTION);
        } else {
            $accountId = get_option(self::ACCOUNT_ID_OPTION);
        }
        
        if (!$accountId) {
            return;
        }
    
        echo "<script>\n"
            . "(function(b,i,d,s){d=document;s=d.createElement('script');\n"
            . "s.async=1;s.src=b;window.PayzardAccountId=i;\n"
            . "window.PayzardBaseUrl='" . self::BASE_URL . "';\n"
            . "d.getElementsByTagName('head')[0].appendChild(s);\n"
            . "})('" . self::BASE_URL . "/payzard.js','" . $accountId . "');\n"
            . "</script>\n";
    }
    
    public function activate($networkWide) {
        $this->checkForSavedActivationError();

        if ($networkWide) {
            update_site_option(self::NETWORK_ENABLED_OPTION, true);
            $accountId = get_site_option(self::ACCOUNT_ID_OPTION);
        } else {
            $accountId = get_option(self::ACCOUNT_ID_OPTION);
        }
        
        if ($accountId) {
            return;
        }
    
        $currentUser = wp_get_current_user();

        $request = array (
            'body' => array (
                'email' => $currentUser->user_email,
                'website' => get_bloginfo('url')
            )
        );
    
        $response = wp_remote_post(self::BASE_URL . '/app/integration/wordpress/install', $request);
        if (is_wp_error($response)) {
            $this->triggerActivationError(array('Unable to communicate with payzard.com', $response->get_error_message()));
        }
        
        $responseObject = json_decode($response['body'], true);
        if (empty($responseObject) || empty($responseObject['accountId'])) {
            $this->triggerActivationError(array('Received invalid response from payzard.com', $response['body']));
        }

        $accountId = $responseObject['accountId'];

        if ($networkWide) {
            update_site_option(self::ACCOUNT_ID_OPTION, $accountId);
        } else {
            update_option(self::ACCOUNT_ID_OPTION, $accountId);
        }
    }
    
    public function deactivate($networkWide) {
        if ($networkWide) {
            update_site_option(self::NETWORK_ENABLED_OPTION, false);
        }
    }
    
    public static function uninstall() {
        delete_site_option(self::ACCOUNT_ID_OPTION);
        delete_site_option(self::NETWORK_ENABLED_OPTION);

        if (is_multisite()) {
            foreach (wp_get_sites() as $blog) {
                delete_blog_option($blog['blog_id'], self::ACCOUNT_ID_OPTION);
            }
        } else {
            delete_option(self::ACCOUNT_ID_OPTION);
        }
    }
    
    private function triggerActivationError($message) {
        if (is_array($message)) {
            $message = implode('<br>', $message);
        }
        
        add_option(self::ACTIVATION_ERROR_OPTION, $message);
        trigger_error($message, E_USER_ERROR);
        exit();
    }
    
    private function checkForSavedActivationError() {
        if (isset($_GET['action']) &&  $_GET['action'] == 'error_scrape') {
            echo 'Error during plugin activation: <br>';
            echo '<strong>' . get_option(self::ACTIVATION_ERROR_OPTION) . '</strong>';
            delete_option(self::ACTIVATION_ERROR_OPTION);
            exit();
        }
    }
}
