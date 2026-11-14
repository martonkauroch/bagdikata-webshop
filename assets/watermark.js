/* Vízjel a megvágott képeken: a feltöltött/beégetett vízjel a kép jobb alsó sarkában van, de a kártyák
   (object-fit: cover) levágják a széleket. Ha a sarok nem látszik, a látható terület sarkára tesszük a logót. */
(function(){
  try{
    var base=(document.currentScript&&document.currentScript.src)?document.currentScript.src.replace(/watermark\.js.*$/,''):'./assets/';
    var map=new WeakMap(),lum=new WeakMap();
    function admin(){return /^#\/admin/.test(location.hash)}
    function overflowHidden(el){var o=getComputedStyle(el);return o.overflow==='hidden'||o.overflowX==='hidden'||o.overflowY==='hidden'}
    function holder(img){
      var p=img.parentElement,best=null;
      while(p&&p!==document.body){
        if(overflowHidden(p)){best=p;break}
        if(p.getBoundingClientRect().width>img.getBoundingClientRect().width*1.02)break;
        p=p.parentElement}
      return best||img.parentElement}
    function dark(img,box,crop){ // a látható jobb alsó sarok világossága
      try{
        var nw=img.naturalWidth,nh=img.naturalHeight,s=Math.max(box.w/nw,box.h/nh);
        var sx=nw-(box.w/s)/2-Math.min(nw*.2,(box.w/s)*.2),sy=nh-(box.h/s)/2-Math.min(nh*.1,(box.h/s)*.1);
        var c=document.createElement('canvas');c.width=c.height=1;var g=c.getContext('2d');
        g.drawImage(img,Math.max(0,sx),Math.max(0,sy),Math.max(1,nw/5),Math.max(1,nh/8),0,0,1,1);
        var d=g.getImageData(0,0,1,1).data;return (0.299*d[0]+0.587*d[1]+0.114*d[2])>=128
      }catch(e){return false}}
    function one(img){
      var ok=img.complete&&img.naturalWidth>=300&&!/\.svg(\?|$)/i.test(img.src);
      var cs=ok?getComputedStyle(img):null;
      if(ok&&cs.objectFit!=='cover')ok=false;
      var r=ok?img.getBoundingClientRect():null;
      if(ok&&(r.width<150||r.height<150))ok=false;
      var m=map.get(img);
      if(!ok||admin()){if(m)m.style.display='none';return}
      var nw=img.naturalWidth,nh=img.naturalHeight,s=Math.max(r.width/nw,r.height/nh);
      var cropR=(nw*s-r.width)/2,cropB=(nh*s-r.height)/2; // a levágott rész (képpont)
      var lost=cropR>r.width*0.015||cropB>r.height*0.015;
      if(!lost||(img.src.indexOf('assets/')>-1&&false)){if(m)m.style.display='none';return}
      var h=holder(img);if(!h)return;
      if(h.__bkwm&&h.__bkwm.img!==img){return}
      if(!m){m=h.__bkwm?h.__bkwm.el:document.createElement('span');m.setAttribute('aria-hidden','true');map.set(img,m);h.__bkwm={img:img,el:m}}
      if(m.parentNode!==h){if(getComputedStyle(h).position==='static')h.style.position='relative';h.appendChild(m)}
      var hr=h.getBoundingClientRect(),k=hr.width/(h.offsetWidth||hr.width)||1;
      var w=Math.max(56,r.width*0.12/k),mg=r.width*0.022/k;
      var badge=h.querySelector('.absolute.bottom-3.right-3'),lift=badge?38:0;
      var right=(hr.right-r.right)/k+mg,bottom=(hr.bottom-r.bottom)/k+mg+lift;
      var d=dark(img,{w:r.width,h:r.height});
      m.style.cssText='position:absolute;pointer-events:none;z-index:2;right:'+right.toFixed(1)+'px;bottom:'+bottom.toFixed(1)+'px;width:'+w.toFixed(1)+'px;height:'+(w*187/1200).toFixed(1)+'px;background:url('+base+(d?'watermark-dark.png':'watermark.png')+') center/contain no-repeat;display:block';
    }
    function run(){var a=document.images;for(var i=0;i<a.length;i++){try{one(a[i])}catch(e){}}}
    var t=0;function req(){if(!t){t=setTimeout(function(){t=0;run()},120)}}
    window.addEventListener('resize',req);window.addEventListener('hashchange',req);window.addEventListener('load',req);
    document.addEventListener('load',req,true);
    setInterval(run,1200);
  }catch(e){}
})();
