<?php
require __DIR__ . '/bootstrap.php';
function workers($operation, $user_id, $reference) {
    $children = array();
    for($i=0;$i<5;$i++) {
        $command = array(PHP_BINARY, __DIR__.'/worker.php', $operation, (string)$user_id, $reference);
        $process = proc_open($command, array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
        fclose($pipes[0]);
        $children[] = array($process,$pipes);
    }
    $accepted = 0;
    foreach($children as $child) {
        list($process,$pipes)=$child;
        $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if(proc_close($process)!==0 || $err) throw new RuntimeException('Worker failed: '.$err);
        if($out==='accepted') $accepted++;
    }
    return $accepted;
}
$id=wp_create_user('race-'.wp_generate_password(8,false,false),'Test-only!123');
CoachPro_Credits::set($id,1);
$accepted=workers('deduct',$id,wp_generate_uuid4());
wp_cache_delete($id,'user_meta');
if($accepted!==1 || CoachPro_Credits::get_balance($id)!==0) throw new RuntimeException('Concurrent deductions overspent balance.');
$pack=CoachPro_DB::get_rows('credit_packs',array(),'created_at ASC',1)[0];
$payment_id=wp_generate_uuid4();
$wpdb->insert(CoachPro_DB::table('payments'),array('id'=>$payment_id,'user_id'=>$id,'kind'=>'credit_pack','pack_id'=>$pack['id'],'amount_pkr'=>$pack['price_pkr'],'credits_grant'=>$pack['credits'],'method'=>'jazzcash'));
$admin=get_user_by('login','testadmin');
$accepted=workers('approve',$admin->ID,$payment_id);
wp_cache_delete($id,'user_meta');
if($accepted!==1 || CoachPro_Credits::get_balance($id)!==(int)$pack['credits']) throw new RuntimeException('Concurrent approval granted twice.');
echo "PASS: Five concurrent deductions spend one credit exactly once.\nPASS: Five concurrent approvals grant one payment exactly once.\n";
