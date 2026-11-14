(function(){
  try{
    var NS='http://www.w3.org/2000/svg';
    var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var root=document.createElement('div');root.id='bk-bg';root.setAttribute('aria-hidden','true');
    root.style.cssText='position:fixed;inset:0;z-index:-1;pointer-events:none;overflow:hidden;background:#f7f4ef';
    function blob(color,size,x,y){var d=document.createElement('div');
      d.style.cssText='position:absolute;left:'+x+'%;top:'+y+'%;width:'+size+'vmax;height:'+size+'vmax;margin:-'+(size/2)+'vmax 0 0 -'+(size/2)+'vmax;border-radius:50%;background:radial-gradient(circle at 50% 50%,'+color+' 0%,rgba(255,255,255,0) 68%);opacity:.55;will-change:transform;filter:blur(18px)';
      root.appendChild(d);return d}
    var blobs=[blob('#f6cfd6',46,12,18),blob('#cfe8da',52,88,42),blob('#f8e7b4',44,28,92),blob('#d8d0ee',40,82,96)];
    var svg=document.createElementNS(NS,'svg');svg.setAttribute('viewBox','0 0 1000 1000');svg.setAttribute('preserveAspectRatio','xMidYMid slice');
    svg.style.cssText='position:absolute;inset:0;width:100%;height:100%;will-change:transform';
    var COL=['#efb3bd','#f5c99c','#f1dc8e','#aedbc2','#a9cbe6','#c6b8e4'];
    var D=[
      'M-60 130 C 160 40, 300 260, 520 170 S 860 60, 1080 190',
      'M-60 330 C 140 430, 330 220, 540 330 S 880 470, 1080 340',
      'M-60 560 C 200 470, 360 700, 560 590 S 840 450, 1080 580',
      'M-60 760 C 160 860, 360 650, 560 770 S 860 880, 1080 740',
      'M-60 940 C 220 840, 380 1010, 600 930 S 880 830, 1080 950',
      'M120 -60 C 60 220, 260 420, 190 700 S 120 930, 210 1080'
    ];
    var strands=D.map(function(d,i){
      var p=document.createElementNS(NS,'path');p.setAttribute('d',d);p.setAttribute('fill','none');
      p.setAttribute('stroke',COL[i]);p.setAttribute('stroke-width',i%2?5:7);p.setAttribute('stroke-linecap','round');p.setAttribute('opacity','.5');
      svg.appendChild(p);
      var dot=document.createElementNS(NS,'circle');dot.setAttribute('r',i%2?7:9);dot.setAttribute('fill',COL[i]);dot.setAttribute('opacity','.75');svg.appendChild(dot);
      return {p:p,dot:dot,len:1,k:i}});
    var stitches=[];
    for(var i=0;i<26;i++){
      var g=document.createElementNS(NS,'path');g.setAttribute('d','M0 0 L7 12 L14 0 M0 7 L7 19 L14 7');g.setAttribute('fill','none');
      g.setAttribute('stroke',COL[i%6]);g.setAttribute('stroke-width','2.4');g.setAttribute('stroke-linecap','round');g.setAttribute('stroke-linejoin','round');
      svg.appendChild(g);
      stitches.push({el:g,x:(i*137)%960+20,y:(i*271)%940+20,sp:.25+((i*7)%10)/14,rot:((i*53)%60)-30,at:(i%13)/13*.85})}
    root.appendChild(svg);
    var cur=0,tgt=0,raf=0;
    function ease(t){return t<0?0:t>1?1:t*t*(3-2*t)}
    function admin(){return /^#\/admin/.test(location.hash)}
    function draw(p){
      strands.forEach(function(s){
        var a=ease((p*1.5+0.18-s.k*0.09)/0.55); if(reduce)a=1;
        s.p.style.strokeDasharray=s.len;s.p.style.strokeDashoffset=(s.len*(1-a)).toFixed(1);
        if(a>0.001&&a<0.999){var pt=s.p.getPointAtLength(s.len*a);s.dot.setAttribute('cx',pt.x);s.dot.setAttribute('cy',pt.y);s.dot.style.opacity=.8}else{s.dot.style.opacity=0}
        s.p.style.opacity=.28+.3*Math.sin(Math.min(1,a)*Math.PI/2)});
      stitches.forEach(function(s){
        var a=ease((p*1.4+0.2-s.at)/0.3); if(reduce)a=.6;
        var y=s.y-p*220*s.sp;
        s.el.setAttribute('transform','translate('+s.x+' '+y.toFixed(1)+') rotate('+(s.rot+p*60*s.sp).toFixed(1)+' 7 10) scale('+(0.7+a*0.5).toFixed(2)+')');
        s.el.style.opacity=(a*0.55).toFixed(2)});
      var h=function(b,i){var dx=(i%2?-1:1)*p*9,dy=-p*(10+i*6);b.style.transform='translate3d('+dx.toFixed(1)+'vw,'+dy.toFixed(1)+'vh,0) scale('+(1+p*(0.25+i*0.05)).toFixed(3)+')'};
      blobs.forEach(h);
      svg.style.transform='translate3d(0,'+(-p*40).toFixed(1)+'px,0)';
    }
    function loop(){raf=0;cur+=(tgt-cur)*0.09;if(Math.abs(tgt-cur)<0.0004)cur=tgt;draw(cur);if(cur!==tgt)raf=requestAnimationFrame(loop)}
    function measure(){var max=Math.max(1,document.documentElement.scrollHeight-window.innerHeight);tgt=Math.min(1,Math.max(0,(window.pageYOffset||0)/max));
      root.style.display=admin()?'none':'block';if(reduce){draw(0.5);return}if(!raf)raf=requestAnimationFrame(loop)}
    function add(){if(!document.body)return setTimeout(add,20);document.body.insertBefore(root,document.body.firstChild);
      strands.forEach(function(s){try{s.len=s.p.getTotalLength()}catch(e){s.len=2000}});
      cur=0;measure();
      window.addEventListener('scroll',measure,{passive:true});window.addEventListener('resize',measure);window.addEventListener('hashchange',function(){setTimeout(measure,50)});
      setInterval(measure,1500)}
    add();
  }catch(e){}
})();
