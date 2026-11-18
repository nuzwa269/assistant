/* Local review motion. Illustrative sample content only; no application requests. */
(function () {
  'use strict';
  var root = document.querySelector('.cpl');
  if (!root) return;
  var reduced = matchMedia('(prefers-reduced-motion: reduce)');
  var desktop = matchMedia('(min-width:901px)');
  var running = [];
  var playing = false;
  var timer;
  var progressAnimation;
  var workspace = root.querySelector('.cpl-workspace');
  var response = root.querySelector('.cpl-demo-response');
  var chapters = Array.from(root.querySelectorAll('.cpl-story-items article'));
  var hero = root.querySelector('.cpl-hero');
  var closing = root.querySelector('.cpl-closing');
  var tools = document.createElement('div');
  tools.className = 'cpl-motion-tools';
  tools.innerHTML = '<button type="button">Replay entrance</button><span class="cpl-motion-status" role="status"></span>';
  root.querySelector('.cpl-hero-bottom').after(tools);
  var status = tools.querySelector('[role=status]');
  var replay = tools.querySelector('button');
  var play = document.createElement('button');
  play.type = 'button';
  play.className = 'cpl-demo-play';
  play.textContent = 'Play sample walkthrough ↗';
  play.setAttribute('aria-pressed', 'false');
  response.after(play);
  function animate(element, frames, options) {
    if (reduced.matches) return null;
    var animation = element.animate(frames, Object.assign({duration:650,easing:'cubic-bezier(.22,1,.36,1)'}, options));
    running.push(animation);
    animation.finished.then(function () { running = running.filter(function (a) { return a !== animation; }); }).catch(function () {});
    return animation;
  }
  function replayEntrance() {
    if (reduced.matches) return;
    root.querySelectorAll('.cpl-line>span').forEach(function (line, index) {
      animate(line, [{transform:'translateY(112%)',opacity:.1},{transform:'translateY(0)',opacity:1}], {duration:1250,delay:index*180,fill:'backwards'});
    });
    animate(root.querySelector('.cpl-hero-bottom'),[{opacity:0,transform:'translateY(24px)'},{opacity:1,transform:'translateY(0)'}],{duration:1100,delay:400,fill:'backwards'});
  }
  replay.addEventListener('click', replayEntrance);
  root.querySelectorAll('[data-demo]').forEach(function (button) {
    button.addEventListener('click',function () {
      running.forEach(function (a) { if(a.effect && a.effect.target===response) a.cancel(); });
      animate(response,[{opacity:.25,transform:'translateY(16px)'},{opacity:1,transform:'translateY(0)'}],{duration:600});
    });
  });
  function stop() {
    playing = false;
    clearTimeout(timer);
    if (progressAnimation) cancelAnimationFrame(progressAnimation);
    response.style.setProperty('--demo-progress','0');
    play.textContent='Play sample walkthrough ↗';
    play.setAttribute('aria-pressed','false');
  }
  play.addEventListener('click',function () {
    if (playing) { stop(); return; }
    playing = true;
    play.textContent='Stop walkthrough';
    play.setAttribute('aria-pressed','true');
    var buttons=Array.from(root.querySelectorAll('[data-demo]'));
    var index=0;
    function next() {
      if (!playing) return;
      buttons[index].click();
      var started=performance.now();
      function tick(now) {
        if (!playing || reduced.matches) return;
        response.style.setProperty('--demo-progress',String(Math.min(1,(now-started)/2400)));
        progressAnimation=requestAnimationFrame(tick);
      }
      if (!reduced.matches) progressAnimation=requestAnimationFrame(tick);
      timer=setTimeout(function(){if(progressAnimation)cancelAnimationFrame(progressAnimation);index++;if(index<buttons.length)next();else stop();},2600);
    }
    next();
  });
  root.querySelectorAll('.cpl-assistant').forEach(function (item) {
    item.addEventListener('toggle',function () {
      if(item.open) animate(item.querySelector('.cpl-assistant-detail'),[{opacity:0,transform:'translateY(-14px)'},{opacity:1,transform:'translateY(0)'}],{duration:650});
    });
  });
  var productObserver = new IntersectionObserver(function(entries){entries.forEach(function(entry){
    if(entry.isIntersecting) workspace.classList.add('product-entered');
    else workspace.classList.remove('product-entered');
  });},{threshold:.22});
  productObserver.observe(workspace);
  var pending=false;
  function frame() {
    pending=false;
    var height=innerHeight;
    var heroRect=hero.getBoundingClientRect();
    var workspaceRect=workspace.getBoundingClientRect();
    var closingRect=closing.getBoundingClientRect();
    var rects=chapters.map(function(item){return item.getBoundingClientRect();});
    var enabled=!reduced.matches&&desktop.matches;
    root.style.setProperty('--hero-drift',enabled?Math.min(85,Math.max(0,-heroRect.top)*.18)+'px':'0px');
    root.style.setProperty('--hero-rotate',enabled?Math.min(9,Math.max(0,-heroRect.top)*.018)+'deg':'0deg');
    root.style.setProperty('--inner-drift',enabled?Math.max(-18,Math.min(18,(height*.5-workspaceRect.top-workspaceRect.height*.5)*-.07))+'px':'0px');
    root.style.setProperty('--closing-drift',enabled?Math.max(-30,Math.min(30,(height*.5-closingRect.top)*.08))+'px':'0px');
    rects.forEach(function(rect,index){
      var progress=Math.max(0,Math.min(1,(height*.8-rect.top)/(rect.height+height*.2)));
      chapters[index].classList.toggle('is-current',rect.top<height*.7&&rect.bottom>height*.2);
      chapters[index].style.setProperty('--chapter-progress',String(reduced.matches?1:progress));
      chapters[index].style.setProperty('--chapter-shift',enabled?(1-progress)*14+'px':'0px');
    });
    if(playing&&(workspaceRect.bottom<0||workspaceRect.top>height))stop();
  }
  function schedule(){if(!pending){pending=true;requestAnimationFrame(frame);}}
  addEventListener('scroll',schedule,{passive:true});
  addEventListener('resize',schedule,{passive:true});
  function preference(){
    stop();
    if(reduced.matches){running.forEach(function(a){a.cancel();});running=[];}
    status.textContent=reduced.matches?'Reduced motion is enabled in your browser / system.':'Motion enabled · scroll to explore';
    replay.disabled=reduced.matches;
    schedule();
  }
  reduced.addEventListener('change',preference);
  document.addEventListener('visibilitychange',function(){if(document.hidden)stop();});
  preference();
})();
