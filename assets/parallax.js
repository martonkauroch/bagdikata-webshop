/* Webshop háttér: pasztell fonalmintás kép, görgetéskor lassabban mozog (parallax). Az adminban nem látszik. */
(function(){
  try{
    var el=document.createElement('div');el.id='bk-bg';el.setAttribute('aria-hidden','true');
    var src=(document.currentScript&&document.currentScript.src)?document.currentScript.src.replace(/parallax\.js.*$/,'bg-fonal.jpg'):'./assets/bg-fonal.jpg';
    el.style.cssText='position:fixed;left:0;right:0;top:0;height:135vh;z-index:-1;pointer-events:none;background:#f6f3ee url('+src+') center top/cover no-repeat;will-change:transform;transform:translate3d(0,0,0)';
    var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var tick=false;
    function admin(){return /^#\/admin/.test(location.hash)}
    function update(){tick=false;
      el.style.display=admin()?'none':'block';
      if(reduce)return;
      var max=Math.max(1,document.documentElement.scrollHeight-window.innerHeight);
      var p=Math.min(1,Math.max(0,(window.pageYOffset||0)/max));
      el.style.transform='translate3d(0,'+(-p*0.35*window.innerHeight).toFixed(1)+'px,0)'}
    function req(){if(!tick){tick=true;window.requestAnimationFrame(update)}}
    window.addEventListener('scroll',req,{passive:true});window.addEventListener('resize',req);window.addEventListener('hashchange',req);
    function add(){if(!document.body)return setTimeout(add,20);document.body.insertBefore(el,document.body.firstChild);update();setInterval(req,1500)}
    add();
  }catch(e){}
})();
