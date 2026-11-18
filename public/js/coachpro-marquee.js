(function () {
  'use strict';
  document.querySelectorAll('.cpl-marquee').forEach(function (section) {
    var viewport=section.querySelector('.cpl-marquee-viewport');
    var track=section.querySelector('.cpl-marquee-track');
    var original=section.querySelector('.cpl-marquee-list');
    var button=section.querySelector('.cpl-marquee-toggle');
    var preference=matchMedia('(prefers-reduced-motion: reduce)');
    var scheduled=false;
    var lastWidth=0;
    function duplicate(node) {
      var copy=node.cloneNode(true);
      copy.setAttribute('aria-hidden','true');
      copy.setAttribute('inert','');
      return copy;
    }
    function build() {
      scheduled=false;
      track.replaceChildren();
      var group=document.createElement('div');
      group.className='cpl-marquee-group';
      group.appendChild(original);
      track.appendChild(group);
      section.classList.toggle('is-running',!preference.matches);
      button.hidden=preference.matches;
      if(preference.matches) return;
      // Each half is identical and at least one viewport wide: no gaps at the seam.
      var width=group.getBoundingClientRect().width;
      if(!width) return;
      var repeats=Math.max(1,Math.ceil(viewport.clientWidth/width));
      for(var i=1;i<repeats;i++) group.appendChild(duplicate(original));
      track.appendChild(duplicate(group));
      section.style.setProperty('--marquee-duration',(group.getBoundingClientRect().width/38)+'s');
    }
    button.addEventListener('click',function(){
      var paused=section.classList.toggle('is-paused');
      button.setAttribute('aria-pressed',String(paused));
      button.textContent=paused?'Resume motion':'Pause motion';
    });
    function schedule(){if(!scheduled){scheduled=true;requestAnimationFrame(build);}}
    if('ResizeObserver' in window) new ResizeObserver(function(entries){
      var width=entries[0].contentRect.width;
      if(Math.abs(width-lastWidth)>1){lastWidth=width;schedule();}
    }).observe(viewport);
    else addEventListener('resize',schedule,{passive:true});
    preference.addEventListener('change',build);
    build();
  });
})();
