<?php
/** Gemini configuration and the dashboard Quick Chat REST sequence. */
require __DIR__ . '/integration.php';
$baseline = count($passed);
$gemini_urls = array();
// Synthetic configurable model IDs; all provider traffic remains mocked.
add_filter('pre_http_request', function($pre, $args, $url) use (&$gemini_urls) {
    if (strpos($url, 'generativelanguage.googleapis.com') === false) return $pre;
    $gemini_urls[] = $url;
    if (!preg_match('#^/v1beta/models/gemini-test-(alpha|beta):generateContent$#', parse_url($url, PHP_URL_PATH))) {
        return new WP_Error('invalid_model_path', 'GenerateContentRequest.model: unexpected model name format', array('status'=>400));
    }
    return $pre;
}, 20, 3);
wp_set_current_user($admin->ID);
update_option('coachpro_gemini_key', 'mock-gemini-key');
$model = success(request('POST', 'admin/models', array('model_id'=>'local-gemini', 'provider_name'=>'gemini', 'api_model_name'=>' models/gemini-test-alpha ', 'credits_cost'=>1)));
check($model['api_model_name'] === 'gemini-test-alpha', 'Gemini creation stores a bare API ID separately from the local model ID');
$model = success(request('PUT', 'admin/models/local-gemini', array('api_model_name'=>'models/gemini-test-beta')));
check($model['api_model_name'] === 'gemini-test-beta', 'Gemini updates normalize resource names without changing the selected model');
foreach (array('Gemini Test Beta', 'google/gemini-test-beta', 'models/models/gemini-test-beta', 'https://example.test/models/gemini-test-beta', '') as $invalid) {
    check(request('PUT', 'admin/models/local-gemini', array('api_model_name'=>$invalid))->get_status() === 400, 'Invalid Gemini configuration is rejected: '.($invalid ?: '(empty)'));
}
check(CoachPro_DB::get_row('ai_models','local-gemini')['api_model_name'] === 'gemini-test-beta', 'Rejected updates preserve the previous configured API model');
success(request('PUT','admin/assistants/'.$assistant['id'],array('default_model_id'=>'local-gemini')));
wp_set_current_user($user_id);
$quick_project = success(request('POST','projects',array('name'=>'Quick Chat','description'=>'Auto-created for quick dashboard chat')));
CoachPro_Credits::set($user_id,10);
foreach (array('gemini-test-alpha', 'models/gemini-test-beta') as $stored_name) {
    // Simulate both existing bare and resource-prefixed records, without re-saving through admin.
    $wpdb->update(CoachPro_DB::table('ai_models'), array('api_model_name'=>$stored_name), array('id'=>'local-gemini'));
    $quick_conv = success(request('POST','conversations',array('project_id'=>$quick_project['id'],'assistant_id'=>$assistant['id'],'title'=>'New conversation')));
    $before = CoachPro_Credits::get_balance($user_id);
    $sent_before = count($gemini_urls);
    // Exact Quick Chat payload: no explicit model_id; use the assistant's configuration.
    $reply = success(request('POST','chat',array('conversation_id'=>$quick_conv['id'],'message'=>'Help me plan my day.')));
    $expected = str_replace('models/', '', $stored_name);
    check(count($gemini_urls) === $sent_before+1 && parse_url(end($gemini_urls),PHP_URL_PATH) === '/v1beta/models/'.$expected.':generateContent', 'Quick Chat sends exactly one models/ prefix for '.$stored_name);
    check($reply['content'] === 'Test answer' && $reply['model_id'] === 'local-gemini' && $reply['balance'] === $before-1 && CoachPro_DB::count('messages',array('conversation_id'=>$quick_conv['id'])) === 2, 'Quick Chat saves the Gemini answer and charges once for '.$stored_name);
}
$sent_before = count($gemini_urls);
$invalid = CoachPro_AI_Provider::call_gemini('https://generativelanguage.googleapis.com/v1beta','mock-key','models/models/invalid',array(array('role'=>'user','content'=>'Hello')));
check(is_wp_error($invalid) && count($gemini_urls) === $sent_before, 'Malformed legacy Gemini names fail before HTTP dispatch');
echo (count($passed)-$baseline)." Gemini regression checks passed.\n";
foreach (array_slice($passed,$baseline) as $line) echo "PASS $line\n";
