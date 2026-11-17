/* Webshop háttér (Admin › Kezdőlap és tartalom › Webshop háttér).
   Stílus: yarn (fonalminta) | blobs (pasztell foltok) | strands (fonalszálak, öltések) | image (saját kép) | plain (egyszínű)
   Mozgás: scroll (görgetésre, parallax) | drift (folyamatos lebegés) | static (mozdulatlan)
   API: window.__bkBgSet(cfg, szín) – élő oldal; window.__bkBgPreview(elem, cfg, szín) – admin előnézet */
(function(){
  try{
    var NS='http://www.w3.org/2000/svg';
    var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var DEF={style:'yarn',motion:'scroll',intensity:1,speed:1,image:'',frame:null};
    var STY={yarn:1,blobs:1,strands:1,image:1,plain:1},MOT={scroll:1,drift:1,static:1};
    function num(v,d,a,b){v=Number(v);if(!isFinite(v))v=d;return Math.min(b,Math.max(a,v))}
    function norm(c){c=c&&typeof c==='object'?c:{};return{
      style:STY[c.style]?c.style:DEF.style,motion:MOT[c.motion]?c.motion:DEF.motion,
      intensity:num(c.intensity,1,.3,2),speed:num(c.speed,1,.5,2),
      image:typeof c.image==='string'?c.image:'',frame:c.frame&&typeof c.frame==='object'?c.frame:null}}
    function color(b){return /^#[0-9a-f]{6}$/i.test(b||'')?b:'#f6f3ee'}
    function imgSrc(u){if(!u)return'';try{var B=window.__BK;if(B&&B.shared&&B.imgUrl&&!/^(https?:|data:|blob:|\.?\/)/.test(u))return B.imgUrl(u)}catch(e){}return u}
    var COL=['#efb3bd','#f5c99c','#f1dc8e','#aedbc2','#a9cbe6','#c6b8e4'];
    var D=[
      'M-60 130 C 160 40, 300 260, 520 170 S 860 60, 1080 190',
      'M-60 330 C 140 430, 330 220, 540 330 S 880 470, 1080 340',
      'M-60 560 C 200 470, 360 700, 560 590 S 840 450, 1080 580',
      'M-60 760 C 160 860, 360 650, 560 770 S 860 880, 1080 740',
      'M-60 940 C 220 840, 380 1010, 600 930 S 880 830, 1080 950',
      'M120 -60 C 60 220, 260 420, 190 700 S 120 930, 210 1080'
    ];
    function ease(t){return t<0?0:t>1?1:t*t*(3-2*t)}

    /* egy háttér-példány: fixed = teljes képernyős (webshop), különben a megadott dobozt tölti ki (előnézet) */
    function make(host,fixed){
      var root=document.createElement('div');root.setAttribute('aria-hidden','true');
      root.style.cssText=(fixed?'position:fixed;z-index:-1;':'position:absolute;z-index:0;')+'inset:0;pointer-events:none;overflow:hidden;background:#f6f3ee';
      if(fixed)root.id='bk-bg';
      var img=document.createElement('div');img.style.cssText='position:absolute;inset:-8%;background-size:cover;background-repeat:no-repeat;will-change:transform;display:none';
      root.appendChild(img);
      var BL=[['#f6cfd6',46,12,18],['#cfe8da',52,88,42],['#f8e7b4',44,28,92],['#d8d0ee',40,82,96]];
      var blobs=BL.map(function(b){var d=document.createElement('div');
        d.style.cssText='position:absolute;border-radius:50%;background:radial-gradient(circle at 50% 50%,'+b[0]+' 0%,rgba(255,255,255,0) 68%);will-change:transform;filter:blur(18px)';
        root.appendChild(d);return{el:d,size:b[1],x:b[2],y:b[3]}});
      var svg=document.createElementNS(NS,'svg');svg.setAttribute('viewBox','0 0 1000 1000');svg.setAttribute('preserveAspectRatio','xMidYMid slice');
      svg.style.cssText='position:absolute;inset:0;width:100%;height:100%;will-change:transform';
      var strands=D.map(function(d,i){
        var p=document.createElementNS(NS,'path');p.setAttribute('d',d);p.setAttribute('fill','none');
        p.setAttribute('stroke',COL[i]);p.setAttribute('stroke-width',i%2?5:7);p.setAttribute('stroke-linecap','round');
        svg.appendChild(p);
        var dot=document.createElementNS(NS,'circle');dot.setAttribute('r',i%2?7:9);dot.setAttribute('fill',COL[i]);dot.style.opacity=0;svg.appendChild(dot);
        return{p:p,dot:dot,len:2000,k:i}});
      var stitches=[];
      for(var i=0;i<26;i++){
        var g=document.createElementNS(NS,'path');g.setAttribute('d','M0 0 L7 12 L14 0 M0 7 L7 19 L14 7');g.setAttribute('fill','none');
        g.setAttribute('stroke',COL[i%6]);g.setAttribute('stroke-width','2.4');g.setAttribute('stroke-linecap','round');g.setAttribute('stroke-linejoin','round');
        svg.appendChild(g);
        stitches.push({el:g,x:(i*137)%960+20,y:(i*271)%940+20,sp:.25+((i*7)%10)/14,rot:((i*53)%60)-30,at:(i%13)/13*.85})}
      root.appendChild(svg);
      if(fixed)host.insertBefore(root,host.firstChild);else host.appendChild(root);
      strands.forEach(function(s){try{s.len=s.p.getTotalLength()}catch(e){}});

      var cfg=norm(null),W=1,H=1;
      function size(){W=fixed?window.innerWidth:host.clientWidth||1;H=fixed?window.innerHeight:host.clientHeight||1;var vm=Math.max(W,H)/100;
        blobs.forEach(function(b){var s=b.size*vm;b.el.style.width=b.el.style.height=s+'px';b.el.style.left=(b.x/100*W-s/2)+'px';b.el.style.top=(b.y/100*H-s/2)+'px'})}
      function apply(c,bg){cfg=norm(c);root.style.background=color(bg);
        var st=cfg.style,showB=st==='yarn'||st==='blobs',showS=st==='yarn'||st==='strands';
        blobs.forEach(function(b){b.el.style.display=showB?'block':'none'});svg.style.display=showS?'block':'none';
        if(st==='image'&&cfg.image){var f=cfg.frame||{};img.style.display='block';img.style.backgroundImage='url("'+imgSrc(cfg.image).replace(/"/g,'%22')+'")';
          img.style.backgroundPosition=(f.x!=null?f.x:50)+'% '+(f.y!=null?f.y:50)+'%';img.style.opacity=Math.min(1,.45*cfg.intensity)}
        else img.style.display='none';
        size()}
      /* p: görgetési arány (0..1), t: idő mp-ben */
      function draw(p,t){
        var I=cfg.intensity,V=cfg.speed,m=reduce?'static':cfg.motion,stat=m==='static',drift=m==='drift';
        if(stat)p=.35;
        if(cfg.style==='image'){
          var tr=stat?'scale(1)':drift?'translate3d('+(Math.sin(t*.05*V)*1.5).toFixed(2)+'%,'+(Math.cos(t*.04*V)*1.5).toFixed(2)+'%,0) scale('+(1.04+.03*Math.sin(t*.06*V)).toFixed(3)+')'
            :'translate3d(0,'+(-p*6*V).toFixed(2)+'%,0) scale(1.04)';
          img.style.transform=tr;return}
        strands.forEach(function(s){
          var a=stat||drift?1:ease((p*1.5+0.18-s.k*0.09)/0.55);
          s.p.style.strokeDasharray=s.len;s.p.style.strokeDashoffset=(s.len*(1-a)).toFixed(1);
          if(!stat&&!drift&&a>0.001&&a<0.999){var pt=s.p.getPointAtLength(s.len*a);s.dot.setAttribute('cx',pt.x);s.dot.setAttribute('cy',pt.y);s.dot.style.opacity=Math.min(1,.8*I)}
          else if(drift){var q=((t*.035*V+s.k*.17)%1),pt2=s.p.getPointAtLength(s.len*q);s.dot.setAttribute('cx',pt2.x);s.dot.setAttribute('cy',pt2.y);s.dot.style.opacity=(Math.min(1,.7*I)*Math.sin(q*Math.PI)).toFixed(2)}
          else s.dot.style.opacity=0;
          s.p.style.opacity=Math.min(1,(.28+.3*Math.sin(Math.min(1,a)*Math.PI/2))*I).toFixed(2)});
        stitches.forEach(function(s){
          var a,y,r;
          if(drift){a=1;y=((s.y-t*14*V*s.sp)%1100+1100)%1100-50;r=s.rot+Math.sin(t*.3*V+s.at*9)*14}
          else{a=stat?.7:ease((p*1.4+0.2-s.at)/0.3);y=s.y-p*220*s.sp*V;r=s.rot+p*60*s.sp}
          s.el.setAttribute('transform','translate('+s.x+' '+y.toFixed(1)+') rotate('+r.toFixed(1)+' 7 10) scale('+(0.7+a*0.5).toFixed(2)+')');
          s.el.style.opacity=Math.min(1,a*0.55*I).toFixed(2)});
        blobs.forEach(function(b,i){var dx,dy,sc;
          if(drift){dx=Math.sin(t*.07*V+i*1.7)*.07*W;dy=Math.cos(t*.055*V+i*2.3)*.06*H;sc=1.1+.12*Math.sin(t*.05*V+i)}
          else{dx=(i%2?-1:1)*p*.09*W*V;dy=-p*(.10+i*.06)*H*V;sc=1+p*(0.25+i*0.05)}
          b.el.style.transform='translate3d('+dx.toFixed(1)+'px,'+dy.toFixed(1)+'px,0) scale('+sc.toFixed(3)+')';b.el.style.opacity=Math.min(1,.55*I).toFixed(2)});
        svg.style.transform=drift?'translate3d('+(Math.sin(t*.09*V)*10).toFixed(1)+'px,'+(Math.cos(t*.07*V)*12).toFixed(1)+'px,0)':'translate3d(0,'+(-p*40*V).toFixed(1)+'px,0)';
      }
      return{root:root,apply:apply,draw:draw,size:size,cfg:function(){return cfg},destroy:function(){root.remove()}};
    }

    /* --- élő webshop háttér --- */
    var main=null,cur=0,tgt=0,raf=0,T0=performance.now();
    function admin(){return /^#\/admin/.test(location.hash)}
    function loop(){raf=0;if(!main)return;var c=main.cfg(),m=reduce?'static':c.motion,hid=main.root.style.display==='none';
      if(m==='scroll'){cur+=(tgt-cur)*0.09;if(Math.abs(tgt-cur)<0.0004)cur=tgt}
      main.draw(cur,(performance.now()-T0)/1000);
      if(!hid&&!document.hidden&&(m==='drift'||(m==='scroll'&&cur!==tgt)))raf=requestAnimationFrame(loop)}
    function measure(){if(!main)return;var max=Math.max(1,document.documentElement.scrollHeight-window.innerHeight);tgt=Math.min(1,Math.max(0,(window.pageYOffset||0)/max));
      main.root.style.display=admin()?'none':'block';if(!raf)raf=requestAnimationFrame(loop)}
    window.__bkBgSet=function(c,bg){window.__bkBgCfg=[c,bg];if(!main)return;main.apply(c,bg);measure()};
    function add(){if(!document.body)return setTimeout(add,20);
      main=make(document.body,true);var pre=window.__bkBgCfg||[];main.apply(pre[0],pre[1]);
      cur=0;measure();
      window.addEventListener('scroll',measure,{passive:true});window.addEventListener('resize',function(){main.size();measure()});
      window.addEventListener('hashchange',function(){setTimeout(measure,50)});document.addEventListener('visibilitychange',measure);
      setInterval(measure,1500)}
    add();

    /* --- admin előnézet: a görgetést automatikus fel-le mozgás mutatja --- */
    window.__bkBgPreview=function(el,c,bg){
      var inst=make(el,false),r=0,on=true,t0=performance.now();inst.apply(c,bg);
      function tick(){if(!on)return;var t=(performance.now()-t0)/1000;inst.draw(.5-.5*Math.cos(t*.45),t);r=requestAnimationFrame(tick)}
      tick();
      var ro=window.ResizeObserver?new ResizeObserver(function(){inst.size()}):null;ro&&ro.observe(el);
      return{set:function(c2,bg2){inst.apply(c2,bg2)},destroy:function(){on=false;cancelAnimationFrame(r);ro&&ro.disconnect();inst.destroy()}}};
  }catch(e){}
})();
