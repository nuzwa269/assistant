<?php
/** Public landing presentation. No private assistant configuration is read. */
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$table = CoachPro_DB::table( 'assistants' );
$assistants = $wpdb->get_results( "SELECT id, name, description, icon, category FROM `{$table}` WHERE is_prebuilt = 1 AND is_active = 1 ORDER BY created_at ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assistants = is_array( $assistants ) ? $assistants : array();
$route = static function ( $name ) {
    $id = (int) get_option( 'coachpro_page_' . $name );
    return $id ? get_permalink( $id ) : '';
};
$login = $route( 'login' );
$start = is_user_logged_in() ? ( $route( 'chat' ) ?: $route( 'dashboard' ) ) : $route( 'register' );
$directory = $route( 'assistants' );
$uid = wp_unique_id( 'cpl-' );
$arrow = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 12h15M13 5l7 7-7 7"/></svg>';
$mark = '<svg viewBox="0 0 36 36" width="36" height="36" fill="none" aria-hidden="true"><rect width="36" height="36" rx="10" fill="#FF870B"/><path d="M24 11h-7a7 7 0 0 0 0 14h7M19 11v14M11 18h15" stroke="#192235" stroke-width="2.5"/></svg>';
$icons = array(
    'GraduationCap' => '<path d="m3 10 11-6 11 6-11 6-11-6Zm4 3v7c5 4 9 4 14 0v-7M25 10v10"/>',
    'Building2' => '<path d="M5 25V7l10-3v21M15 11h8v14M3 25h22M9 9v2m0 3v2m0 3v2m10-7v2m0 3v2"/>',
    'Lightbulb' => '<path d="M10 21h8m-7 4h6M10 18c0-3-4-4-4-8a8 8 0 0 1 16 0c0 4-4 5-4 8H10Z"/>',
);
$fallback_icon = '<path d="M14 3v22M3 14h22M6 6l16 16M22 6 6 22"/><circle cx="14" cy="14" r="8"/>';
$cta = static function ( $label, $url, $class = '' ) use ( $arrow ) {
    if ( ! $url ) return; // Do not invent a route when a WordPress page is unconfigured.
    echo '<a class="cpl-button ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
};
?>
<div class="cpl" id="<?php echo esc_attr( $uid ); ?>">
  <a class="cpl-skip" href="#<?php echo esc_attr( $uid ); ?>-main">Skip to content</a>
  <header class="cpl-nav">
    <a class="cpl-brand" href="#<?php echo esc_attr( $uid ); ?>"><?php echo $mark; ?><span>CoachPro <b>AI</b></span></a>
    <details class="cpl-menu" open>
      <summary>Menu <span aria-hidden="true">＋</span></summary>
      <nav aria-label="Landing page"><a href="#<?php echo esc_attr( $uid ); ?>-assistants">AI Assistants</a><a href="#<?php echo esc_attr( $uid ); ?>-workspace">The workspace</a><a href="#<?php echo esc_attr( $uid ); ?>-how">How it works</a><?php if ( $login ) : ?><a href="<?php echo esc_url( $login ); ?>">Log in</a><?php endif; ?><?php $cta( 'Get Started', $start, 'cpl-small' ); ?></nav>
    </details>
  </header>
  <main id="<?php echo esc_attr( $uid ); ?>-main">
    <section class="cpl-hero cpl-container">
      <div class="cpl-eyebrow"><span class="cpl-dot"></span> A little direction. A lot of possibility.</div>
      <div class="cpl-hero-grid">
        <h1><span class="cpl-line"><span>Your ideas.</span></span><span class="cpl-line"><span>Your <em>AI assistants.</em></span></span><span class="cpl-line"><span>One workspace<span class="cpl-orange">.</span></span></span></h1>
        <div class="cpl-hero-note"><span class="cpl-note-symbol" aria-hidden="true"><svg width="60" height="60" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.5"><?php echo $fallback_icon; ?></svg></span><p>From the first question<br>to the next useful thing.</p><span class="cpl-note-line" aria-hidden="true"></span></div>
      </div>
      <div class="cpl-hero-bottom"><p>Bring your thinking into focus. Find specialized AI assistants, develop prompts, and keep your projects and conversations together in CoachPro AI.</p><div class="cpl-actions"><?php $cta( 'Get Started', $start ); ?><a class="cpl-text-link" href="#<?php echo esc_attr( $uid ); ?>-assistants">Explore AI Assistants <span aria-hidden="true">↗</span></a></div></div>
      <div class="cpl-section-meta"><span>MADE FOR THE WORK YOU DO</span><span>Think. Create. Keep going. ↓</span></div>
    </section>

    <?php if ( $assistants ) : ?>
    <section class="cpl-marquee" aria-label="Explore our AI assistants">
      <div class="cpl-container cpl-marquee-heading"><span class="cpl-eyebrow">SPECIALISTS FOR YOUR NEXT IDEA</span><button class="cpl-marquee-toggle" type="button" aria-pressed="false" hidden>Pause motion</button></div>
      <div class="cpl-marquee-viewport" tabindex="0" aria-label="AI assistant names. Motion pauses while focused."><div class="cpl-marquee-track"><div class="cpl-marquee-group"><ul class="cpl-marquee-list">
        <?php foreach ( $assistants as $i => $assistant ) : ?><li><span><?php echo esc_html( $assistant['name'] ); ?></span><svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M10 2v16M2 10h16M4 4l12 12M16 4 4 16"/></svg></li><?php endforeach; ?>
      </ul></div></div></div>
    </section>
    <?php endif; ?>

    <section class="cpl-demo-section" id="<?php echo esc_attr( $uid ); ?>-workspace" aria-labelledby="<?php echo esc_attr( $uid ); ?>-demo-title">
      <div class="cpl-container"><div class="cpl-demo-caption"><h2 id="<?php echo esc_attr( $uid ); ?>-demo-title">Space for your next <em>“what if?”</em></h2><span>ILLUSTRATIVE WORKSPACE · SAMPLE CONTENT</span></div>
      <div class="cpl-workspace" data-reveal>
        <aside class="cpl-sidebar" aria-label="Illustrative workspace sidebar"><div class="cpl-brand"><?php echo $mark; ?><span>CoachPro AI</span></div><span class="cpl-side-label">YOUR WORKSPACE</span><span class="cpl-side-item cpl-side-selected">↗ &nbsp; Conversation</span><span class="cpl-side-item">▱ &nbsp; Projects</span><span class="cpl-side-item">▤ &nbsp; Saved responses</span><div class="cpl-sidebar-bottom">A place to pick up<br>where you left off.</div></aside>
        <div class="cpl-demo-main"><div class="cpl-demo-top"><span>Idea notebook <span aria-hidden="true">/</span> A fresh start</span><span class="cpl-sample">Example</span></div><div class="cpl-demo-content"><div class="cpl-demo-avatar" aria-hidden="true"><?php echo $mark; ?></div><p class="cpl-eyebrow">A THOUGHT BECOMES A STARTING POINT</p><h3>What would you like<br>to work on?</h3><div class="cpl-demo-controls" aria-label="Explore sample workflows"><button type="button" data-demo="prompt" aria-pressed="true">Create a prompt</button><button type="button" data-demo="project" aria-pressed="false">Shape a project</button><button type="button" data-demo="saved" aria-pressed="false">Revisit an idea</button></div><div class="cpl-demo-response" aria-live="polite" aria-atomic="true"><span class="cpl-eyebrow" data-demo-label>SAMPLE PROMPT</span><p data-demo-copy>Help me turn a broad idea into a clear brief. Ask about my audience, my goal, and the format I need.</p><span class="cpl-demo-response-foot">Your context is the starting point. <span aria-hidden="true">↗</span></span></div><div class="cpl-composer" aria-label="Illustrative message composer">Start with a question or an idea… <span aria-hidden="true">↑</span></div></div></div>
      </div><div class="cpl-demo-foot"><span>Specialized assistants. Connected thinking.</span><span>Preview only — your work starts after sign-in.</span></div></div>
    </section>

    <section class="cpl-assistants cpl-container" id="<?php echo esc_attr( $uid ); ?>-assistants">
      <div class="cpl-section-heading" data-reveal><div><p class="cpl-eyebrow">01 / MEET YOUR NEXT THINKING PARTNER</p><h2>A different perspective.<br><em>The right assistant.</em></h2></div><p>Choose a specialist for the task in front of you. Explore the assistants currently available in CoachPro AI.</p></div>
      <div class="cpl-assistant-index">
      <?php foreach ( $assistants as $i => $assistant ) : ?>
        <details class="cpl-assistant" <?php echo 0 === $i ? 'open' : ''; ?>>
          <summary><span class="cpl-assistant-number"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span><span class="cpl-assistant-glyph" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.5"><?php echo $icons[ $assistant['icon'] ] ?? $fallback_icon; ?></svg></span><span class="cpl-assistant-title"><?php echo esc_html( $assistant['name'] ); ?></span><?php if ( ! empty( $assistant['category'] ) ) : ?><span class="cpl-category"><?php echo esc_html( $assistant['category'] ); ?></span><?php endif; ?><span class="cpl-expand" aria-hidden="true">＋</span></summary>
          <div class="cpl-assistant-detail"><p><?php echo esc_html( wp_strip_all_tags( $assistant['description'] ?? '' ) ); ?></p><?php $cta( 'Explore ' . $assistant['name'], $directory, 'cpl-outline' ); ?></div>
        </details>
      <?php endforeach; ?>
      <?php if ( ! $assistants ) : ?><p class="cpl-empty">The assistant collection is being updated. Check the workspace for availability.</p><?php endif; ?>
      </div><p class="cpl-fine">Choose and activate assistants in the workspace. Availability and activation limits depend on your plan.</p>
    </section>

    <section class="cpl-story" id="<?php echo esc_attr( $uid ); ?>-features"><div class="cpl-container cpl-story-grid"><div class="cpl-story-heading"><p class="cpl-eyebrow">02 / LESS SCATTER. MORE CONTEXT.</p><h2>Give your ideas<br>a place to<br><em>come together.</em></h2><div class="cpl-orbit" aria-hidden="true"><svg viewBox="0 0 300 170" fill="none"><path d="M20 85h260M150 12v146" stroke="currentColor" stroke-opacity=".25"/><ellipse cx="150" cy="85" rx="115" ry="42" stroke="currentColor" transform="rotate(-25 150 85)"/><ellipse cx="150" cy="85" rx="115" ry="42" stroke="currentColor" transform="rotate(25 150 85)"/><rect x="130" y="65" width="40" height="40" rx="10" fill="#FF870B"/><path d="M141 85h18m-9-9v18" stroke="#192235" stroke-width="2"/></svg></div></div><div class="cpl-story-items"><article data-reveal><span>01 — FIND YOUR FOCUS</span><h3>A specialist.<br>A clearer starting point.</h3><p>Start with an assistant built around a particular area of work, rather than figuring out every conversation from scratch.</p></article><article data-reveal><span>02 — BUILD ON THE CONVERSATION</span><h3>Work through it.<br>One question at a time.</h3><p>Develop a prompt, explore a direction, and refine your thinking through conversation. Bring the context that makes the answer useful to you.</p></article><article data-reveal><span>03 — KEEP THE THREAD</span><h3>Good ideas deserve<br>somewhere to live.</h3><p>Organize conversations in projects and save useful responses. Return to your work when you’re ready for the next step.</p></article></div></div></section>

    <section class="cpl-how cpl-container" id="<?php echo esc_attr( $uid ); ?>-how"><div class="cpl-section-heading" data-reveal><div><p class="cpl-eyebrow">03 / FROM CURIOUS TO CREATING</p><h2>Start with a question.<br><em>See where it takes you.</em></h2></div><p>No elaborate setup. Choose your assistant, give it context, and build from there.</p></div><div class="cpl-journey"><div class="cpl-progress" aria-hidden="true"><span></span></div><?php
      $steps = array(
        array( 'Choose your assistant', 'Browse the assistant collection and activate a specialist within your plan.', 'ASSISTANT → ACTIVATED' ),
        array( 'Open a conversation', 'Start from Dashboard or Chat. Select your assistant and share what you want to work on.', 'YOUR QUESTION → A STARTING POINT' ),
        array( 'Make room for the work', 'Develop prompts and keep related conversations together in a project.', 'CONVERSATION → PROJECT' ),
        array( 'Keep what’s useful', 'Save responses and revisit your conversations when you want to continue.', 'SAVE → REVISIT → REFINE' ),
      );
      foreach ( $steps as $i => $step ) : ?><article class="cpl-step" data-reveal><span class="cpl-step-number"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span><h3><?php echo esc_html( $step[0] ); ?></h3><p><?php echo esc_html( $step[1] ); ?></p><span class="cpl-step-caption"><?php echo esc_html( $step[2] ); ?></span></article><?php endforeach; ?></div></section>

    <section class="cpl-transparency cpl-container" data-reveal><span class="cpl-eyebrow">A NOTE ON AI</span><p>Your judgment belongs in the loop. AI responses can be incomplete or incorrect. Review important outputs and bring your own expertise to the final decision.</p></section>
    <section class="cpl-closing"><div class="cpl-container"><p class="cpl-eyebrow">THE NEXT CHAPTER STARTS WITH YOU</p><h2>Your next great idea<br>deserves the right<br><em>AI assistant.</em><span aria-hidden="true">↗</span></h2><div class="cpl-closing-bottom"><p>Bring an idea. Find your specialist.<br>Make something worth keeping.</p><div class="cpl-actions"><?php $cta( 'Get Started', $start, 'cpl-dark-button' ); ?><a class="cpl-text-link" href="#<?php echo esc_attr( $uid ); ?>-assistants">Explore AI Assistants ↗</a></div></div></div></section>
  </main>
  <footer class="cpl-footer cpl-container"><a class="cpl-brand" href="#<?php echo esc_attr( $uid ); ?>"><?php echo $mark; ?><span>CoachPro <b>AI</b></span></a><p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> CoachPro AI</p><div><?php if ( $login ) : ?><a href="<?php echo esc_url( $login ); ?>">Log in</a><?php endif; ?><?php if ( get_privacy_policy_url() ) : ?><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Privacy</a><?php endif; ?><a href="#<?php echo esc_attr( $uid ); ?>">Back to top ↑</a></div></footer>
</div>
