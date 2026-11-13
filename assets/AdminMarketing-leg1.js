import{$ as e,Z as t,i as n}from"./createLucideIcon-Bc0h7bbI.js";
import{j as useStore,W as useSettings,A as useContent}from"./index-0f-fFOYT.js";
import{t as PageHeader}from"./PageHeader-BYypCJGs.js";
var N=e(t(),1),P=n();
var h=(type,props,...kids)=>{let{key:k,...rest}=props||{};if(kids.length===1)rest.children=kids[0];else if(kids.length>1)rest.children=kids;return k===void 0?P.jsx(type,rest):P.jsx(type,rest,k)};
var NF=new Intl.NumberFormat("hu-HU");
var shared=()=>!!(window.__BK&&window.__BK.shared&&window.__BK.admin);
var api=(path,body)=>fetch(window.__BK.base+"api/"+path,{method:"POST",credentials:"same-origin",headers:{"X-BK":"1","Content-Type":"application/json"},body:JSON.stringify(body)}).then(r=>r.json().catch(()=>({})).then(j=>{if(!r.ok||!j||!j.ok)throw new Error(j&&j.error||("Hiba ("+r.status+")"));return j}));
var fmtDate=s=>{if(!s)return"–";try{let d=new Date(s);return d.toLocaleDateString("hu-HU",{year:"numeric",month:"2-digit",day:"2-digit"})+" "+d.toLocaleTimeString("hu-HU",{hour:"2-digit",minute:"2-digit"})}catch{return s}};
var S={
  card:{background:"#fff",border:"1px solid #e5e7eb",borderRadius:12,boxShadow:"0 1px 2px rgba(0,0,0,.05)",marginBottom:24},
  head:{borderBottom:"1px solid #e5e7eb",padding:"14px 20px"},
  body:{padding:20},
  h2:{fontSize:16,fontWeight:600,margin:0},
  sub:{fontSize:13,color:"#6b7280",margin:"4px 0 0"},
  grid:{display:"grid",gap:12,gridTemplateColumns:"repeat(auto-fit,minmax(150px,1fr))"},
  kpi:{border:"1px solid #e5e7eb",borderRadius:10,padding:"12px 14px",background:"#fff"},
  kpiN:{fontSize:24,fontWeight:600,lineHeight:1.1},kpiL:{fontSize:12,color:"#6b7280",marginTop:2},
  th:{textAlign:"left",fontSize:12,fontWeight:500,color:"#6b7280",padding:"8px 10px",borderBottom:"1px solid #e5e7eb",whiteSpace:"nowrap"},
  td:{padding:"9px 10px",borderBottom:"1px solid #f3f4f6",fontSize:14,verticalAlign:"middle"},
  btn:{border:"1px solid #d1d5db",background:"#fff",borderRadius:8,padding:"6px 12px",fontSize:13,fontWeight:500,cursor:"pointer",color:"#111827"},
  btnD:{border:"1px solid #111827",background:"#111827",color:"#fff",borderRadius:8,padding:"8px 16px",fontSize:14,fontWeight:500,cursor:"pointer"},
  btnR:{border:"1px solid #fca5a5",background:"#fff",color:"#b91c1c",borderRadius:8,padding:"6px 12px",fontSize:13,fontWeight:500,cursor:"pointer"},
  btnG:{border:"1px solid #86efac",background:"#fff",color:"#15803d",borderRadius:8,padding:"6px 12px",fontSize:13,fontWeight:500,cursor:"pointer"},
  inp:{width:"100%",border:"1px solid #d1d5db",borderRadius:8,padding:"8px 10px",fontSize:14,background:"#fff",boxSizing:"border-box"},
  lab:{display:"block",fontSize:13,fontWeight:500,marginBottom:4,color:"#374151"},
  err:{background:"#fef2f2",color:"#b91c1c",border:"1px solid #fecaca",borderRadius:8,padding:"8px 12px",fontSize:13,margin:"0 0 12px"},
  ok:{background:"#ecfdf5",color:"#047857",border:"1px solid #a7f3d0",borderRadius:8,padding:"8px 12px",fontSize:13,margin:"0 0 12px"},
  info:{background:"#eff6ff",color:"#1e3a8a",border:"1px solid #bfdbfe",borderRadius:8,padding:"10px 14px",fontSize:13,lineHeight:1.5,margin:"0 0 20px"},
  warn:{background:"#fffbeb",color:"#78350f",border:"1px solid #fde68a",borderRadius:8,padding:"10px 14px",fontSize:13,lineHeight:1.5,margin:"0 0 16px"}
};
var Badge=({tone,children})=>{let c={green:["#ecfdf5","#047857"],red:["#fef2f2","#b91c1c"],amber:["#fffbeb","#b45309"],gray:["#f3f4f6","#4b5563"]}[tone]||["#f3f4f6","#4b5563"];return h("span",{style:{background:c[0],color:c[1],borderRadius:999,padding:"2px 10px",fontSize:12,fontWeight:600,whiteSpace:"nowrap"}},children)};
var Card=({title,description,children})=>h("section",{style:S.card},h("header",{style:S.head},h("h2",{style:S.h2},title),description&&h("p",{style:S.sub},description)),h("div",{style:S.body},children));
var NeedShared=()=>h("div",{style:S.warn},"Ez a funkció csak az éles, szerveres működésnél érhető el (api mappa feltöltve, admin belépéssel).");
var scroll={overflowX:"auto"};

/* ======================= KUPONOK ======================= */
function genCode(){let a="ABCDEFGHJKLMNPQRSTUVWXYZ23456789",s="";let r=new Uint32Array(5);crypto.getRandomValues(r);for(let i=0;i<5;i++)s+=a[r[i]%a.length];return"KATA-"+s}
function CouponsPage(){
  let{orders}=useStore();
  let[list,setList]=N.useState(null),[err,setErr]=N.useState(null),[ok,setOk]=N.useState(null),[busy,setBusy]=N.useState(false);
  let[f,setF]=N.useState({code:"",percent:"10",max:"20",note:""}),[ed,setEd]=N.useState(null);
  let load=()=>shared()?api("coupons.php",{action:"list"}).then(j=>setList(j.coupons)).catch(e=>setErr(e.message)):0;
  N.useEffect(()=>{load();let t=window.setInterval(load,20000);return()=>window.clearInterval(t)},[]);
  let act=(body,msg)=>{setErr(null);setOk(null);setBusy(true);return api("coupons.php",body).then(j=>{setList(j.coupons);if(msg)setOk(msg);return true}).catch(e=>{setErr(e.message);return false}).finally(()=>setBusy(false))};
  let stats=N.useMemo(()=>{let m={};for(let o of orders||[]){if(!o.coupon||!o.coupon.code||o.coupon.invalid)continue;let k=o.coupon.code,x=m[k]||(m[k]={n:0,sum:0,cancelled:0});if(o.status==="torolve")x.cancelled++;else{x.n++;x.sum+=Number(o.coupon.discount)||0}}return m},[orders]);
  let create=ev=>{ev.preventDefault();act({action:"create",code:f.code,percent:Number(f.percent),max:Number(f.max)||0,note:f.note},"A kupon létrejött: "+f.code.trim().toUpperCase()).then(r=>{if(r)setF({code:"",percent:f.percent,max:f.max,note:""})})};
  let del=c=>{if(window.confirm("Biztosan törlöd a(z) "+c.code+" kupont? A már leadott rendelések nem változnak, de a kód ezután ismeretlen lesz."))act({action:"delete",code:c.code},"Törölve: "+c.code)};
  let state=c=>c.disabled?["red","Letiltva"]:(c.max>0&&c.used>=c.max?["amber","Elfogyott"]:["green","Aktív"]);
  let saveEd=()=>act({action:"update",code:ed.code,percent:Number(ed.percent),max:Number(ed.max)||0,note:ed.note},"Mentve: "+ed.code).then(r=>{if(r)setEd(null)});
  let copy=c=>{try{navigator.clipboard.writeText(c);setOk("Kimásolva: "+c)}catch{}};
  return h("div",null,
    h(PageHeader,{title:"Kuponok",description:"Százalékos kedvezmény kuponkóddal. A vásárló a pénztárban váltja be; a számláló a leadott rendeléseknél nő."}),
    !shared()&&h(NeedShared),
    err&&h("p",{style:S.err,role:"alert"},err),ok&&h("p",{style:S.ok,role:"status"},ok),
    h(Card,{title:"Új kupon",description:"A kód nagybetűvel, szóköz nélkül (betű, szám, kötőjel). A kedvezmény a termékek értékéből jön, a szállítási díjból nem."},
      h("form",{onSubmit:create,"data-testid":"coupon-form"},
        h("div",{style:{display:"grid",gap:12,gridTemplateColumns:"repeat(auto-fit,minmax(170px,1fr))",alignItems:"end"}},
          h("label",null,h("span",{style:S.lab},"Kuponkód"),h("div",{style:{display:"flex",gap:6}},h("input",{style:{...S.inp,textTransform:"uppercase"},value:f.code,maxLength:24,placeholder:"pl. NYAR10",onChange:ev=>setF({...f,code:ev.target.value.replace(/\s/g,"")}),"data-testid":"coupon-code"}),h("button",{type:"button",style:S.btn,onClick:()=>setF({...f,code:genCode()}),title:"Véletlen kód generálása"},"Generál"))),
          h("label",null,h("span",{style:S.lab},"Kedvezmény (%)"),h("input",{style:S.inp,type:"number",min:1,max:90,value:f.percent,onChange:ev=>setF({...f,percent:ev.target.value}),"data-testid":"coupon-percent"})),
          h("label",null,h("span",{style:S.lab},"Mennyiség (db, 0 = korlátlan)"),h("input",{style:S.inp,type:"number",min:0,max:100000,value:f.max,onChange:ev=>setF({...f,max:ev.target.value}),"data-testid":"coupon-max"})),
          h("label",null,h("span",{style:S.lab},"Megjegyzés (csak neked látszik)"),h("input",{style:S.inp,value:f.note,maxLength:120,onChange:ev=>setF({...f,note:ev.target.value})}))),
        h("div",{style:{marginTop:14}},h("button",{type:"submit",style:{...S.btnD,opacity:busy||!shared()?.5:1},disabled:busy||!shared(),"data-testid":"coupon-create"},"Kupon létrehozása")))),
    h(Card,{title:"Kuponok ("+(list?list.length:0)+")",description:"A letiltott vagy elfogyott kupont a pénztár „Ez a kupon már nem érvényes” üzenettel utasítja el – a rendelés leadásakor is újra ellenőrzi."},
      !list?h("p",{style:S.sub},shared()?"Betöltés…":"–"):list.length===0?h("p",{style:S.sub},"Még nincs kupon. Hozz létre egyet fent."):
      h("div",{style:scroll},h("table",{style:{width:"100%",borderCollapse:"collapse",minWidth:760},"data-testid":"coupon-table"},
        h("thead",null,h("tr",null,["Kupon","Kedvezmény","Felhasználva","Maradt","Adott kedvezmény","Állapot","",""].map((x,i)=>h("th",{key:i,style:S.th},x)))),
        h("tbody",null,list.map(c=>{let[tone,lbl]=state(c),st=stats[c.code]||{n:0,sum:0,cancelled:0},isEd=ed&&ed.code===c.code,left=c.max>0?Math.max(0,c.max-c.used):null,pct=c.max>0?Math.min(100,Math.round(c.used/c.max*100)):0;
          return h("tr",{key:c.code,"data-testid":"coupon-row"},
            h("td",{style:S.td},h("div",{style:{fontFamily:"ui-monospace,Menlo,monospace",fontWeight:700,letterSpacing:.5}},c.code," ",h("button",{type:"button",style:{...S.btn,padding:"1px 7px",fontSize:11},onClick:()=>copy(c.code)},"másol")),isEd?h("input",{style:{...S.inp,marginTop:6},value:ed.note,maxLength:120,placeholder:"megjegyzés",onChange:ev=>setEd({...ed,note:ev.target.value})}):c.note&&h("div",{style:{fontSize:12,color:"#6b7280",marginTop:2}},c.note)),
            h("td",{style:S.td},isEd?h("input",{style:{...S.inp,width:70},type:"number",min:1,max:90,value:ed.percent,onChange:ev=>setEd({...ed,percent:ev.target.value})}):"−"+c.percent+"%"),
            h("td",{style:S.td},h("div",{style:{fontWeight:600}},"Eddig "+NF.format(c.used)+" db"+(c.max>0?" / "+NF.format(c.max):"")),c.max>0&&h("div",{style:{height:5,background:"#e5e7eb",borderRadius:3,marginTop:4,width:110}},h("div",{style:{height:5,borderRadius:3,width:pct+"%",background:pct>=100?"#d97706":"#4f46e5"}})),st.cancelled>0&&h("div",{style:{fontSize:11,color:"#6b7280",marginTop:2}},"ebből törölt rendelés: "+st.cancelled)),
            h("td",{style:S.td},isEd?h("input",{style:{...S.inp,width:90},type:"number",min:0,value:ed.max,title:"0 = korlátlan",onChange:ev=>setEd({...ed,max:ev.target.value})}):(left===null?"korlátlan":NF.format(left)+" db")),
            h("td",{style:S.td},st.n>0?NF.format(st.sum)+" Ft ("+st.n+" rend.)":"–"),
            h("td",{style:S.td},h(Badge,{tone},lbl)),
            h("td",{style:{...S.td,whiteSpace:"nowrap"}},c.disabled?h("button",{type:"button",style:S.btnG,disabled:busy,onClick:()=>act({action:"toggle",code:c.code,disabled:false},"Engedélyezve: "+c.code),"data-testid":"coupon-enable"},"Engedélyezés"):h("button",{type:"button",style:S.btnR,disabled:busy,onClick:()=>act({action:"toggle",code:c.code,disabled:true},"Letiltva: "+c.code+" – a pénztár már nem fogadja el."),"data-testid":"coupon-disable"},"Letiltás")),
            h("td",{style:{...S.td,whiteSpace:"nowrap"}},isEd?h("span",null,h("button",{type:"button",style:S.btnD,onClick:saveEd},"Mentés")," ",h("button",{type:"button",style:S.btn,onClick:()=>setEd(null)},"Mégse")):h("span",null,h("button",{type:"button",style:S.btn,onClick:()=>setEd({code:c.code,percent:String(c.percent),max:String(c.max),note:c.note||""})},"Szerkeszt")," ",h("button",{type:"button",style:S.btn,onClick:()=>del(c)},"Töröl"))))})))),
      h("p",{style:{...S.sub,marginTop:12}},"A „Felhasználva” számláló a leadott rendelésekkel nő (törölt rendelésnél nem csökken). A „Adott kedvezmény” a nem törölt rendeléseken adott összeg.")));
}

/* ======================= HÍRLEVÉL ======================= */
var SRC={checkout:"Pénztár",footer:"Weboldal"},STAT={confirmed:["green","Megerősített"],pending:["amber","Megerősítésre vár"],unsubscribed:["gray","Leiratkozott"]};
var CSTAT={sending:["amber","Folyamatban"],paused:["red","Szünetel"],done:["green","Kész"],stopped:["gray","Leállítva"]};
function NewsletterPage(){
  let[data,setData]=N.useState(null),[err,setErr]=N.useState(null),[ok,setOk]=N.useState(null);
  let[f,setF]=N.useState({subject:"",body:"",subjectEn:"",bodyEn:"",ctaLabel:"",ctaUrl:""}),[en,setEn]=N.useState(false);
  let[run,setRun]=N.useState(null),[q,setQ]=N.useState(""),[sf,setSf]=N.useState("all"),[lim,setLim]=N.useState(50);
  let stop=N.useRef(false);
  let load=()=>shared()?api("newsletter.php",{action:"list"}).then(setData).catch(e=>setErr(e.message)):0;
  N.useEffect(()=>{load();let t=window.setInterval(()=>{if(!run)load()},30000);return()=>window.clearInterval(t)},[run]);
  let[trBusy,setTrBusy]=N.useState(false);
  let trNow=()=>{if(!f.subject.trim()&&!f.body.trim())return;setTrBusy(true);window.__bkTrItems([{hu:f.subject,en:""},{hu:f.body,en:""}]).then(r=>{setTrBusy(false);if(window.__bkTrErr&&window.__bkTrErr()){window.__bkToast&&window.__bkToast(window.__bkTrErr(),1);setErr("Az angol fordítás nem sikerült: "+window.__bkTrErr());return}setErr("");setF(x=>({...x,subjectEn:r[0]||x.subjectEn,bodyEn:r[1]||x.bodyEn}))})};
  let payload=()=>({subject:f.subject,body:f.body,subjectEn:en?f.subjectEn:"",bodyEn:en?f.bodyEn:"",ctaLabel:f.ctaLabel,ctaUrl:f.ctaUrl});
  let test=lang=>{setErr(null);setOk(null);api("newsletter.php",{action:"test",lang,...payload()}).then(j=>setOk("A tesztlevél elment ide: "+j.to+" (a lábléc leiratkozó linkje a teszten nem működik).")).catch(e=>setErr(e.message))};
  let runCampaign=async id=>{stop.current=false;setRun({id});try{for(;;){let r=await api("newsletter.php",{action:"campaign_send",id});setData(r);let c=r.campaigns.find(x=>x.id===id);if(!c||c.status!=="sending"||c.remaining===0){if(c)setOk("Kiküldés kész: "+c.sent+" elküldve"+(c.failed?", "+c.failed+" sikertelen":"")+".");if(c&&c.status==="paused")setErr("A kiküldés megszakadt: "+(c.lastError||"levélküldési hiba")+". A Folytatás gombbal újrapróbálhatod (a tárhelyek óránkénti levélkorlátja miatt érdemes várni).");break}if(stop.current){setOk("A kiküldés szünetel – a Folytatás gombbal újraindíthatod.");break}}}catch(e){setErr(e.message)}setRun(null);load()};
  let send=()=>{setErr(null);setOk(null);let n=data?data.counts.confirmed:0;if(!f.subject.trim()||!f.body.trim()){setErr("A tárgy és a levél szövege kötelező.");return}
    if(!window.confirm("Elküldöd a hírlevelet "+n+" megerősített feliratkozónak? A kiküldés nem vonható vissza. Javasolt előbb tesztlevelet küldeni magadnak."))return;
    api("newsletter.php",{action:"campaign_create",...payload()}).then(r=>{setData(r);runCampaign(r.campaignId)}).catch(e=>setErr(e.message))};
  let campAct=(action,id)=>api("newsletter.php",{action,id}).then(setData).catch(e=>setErr(e.message));
  let subAct=(action,s)=>{let m=action==="delete"?"Véglegesen törlöd ezt a címet ("+s.email+")? (Adattörlési kérésnél ez a teendő.)":"Leiratkoztatod ezt a címet ("+s.email+")? Többé nem kap hírlevelet.";if(window.confirm(m))api("newsletter.php",{action,id:s.id}).then(setData).catch(e=>setErr(e.message))};
  let subs=data?data.subscribers.filter(s=>(sf==="all"||s.status===sf)&&(!q.trim()||(s.email+" "+s.name).toLowerCase().includes(q.trim().toLowerCase()))):[];
  let cnt=data?data.counts:{confirmed:0,pending:0,unsubscribed:0};
  return h("div",null,
    h(PageHeader,{title:"Hírlevél",description:"Feliratkozók gyűjtése (pénztár és lábléc), jogszerű nyilvántartás és hírlevél-küldés."}),
    !shared()&&h(NeedShared),
    err&&h("p",{style:S.err,role:"alert"},err),ok&&h("p",{style:S.ok,role:"status"},ok),
    h("div",{style:S.info},h("strong",null,"Hogyan jogszerű? "),"Hírlevelet csak olyannak küldhetsz, aki kifejezetten kérte (Grt. 6. §, GDPR). Ezért a pénztári jelölőnégyzet alapból üres, a feliratkozó e-mailben megerősíti a címét (kettős megerősítés), a hozzájárulás időpontját és forrását eltároljuk, és minden levél alján ott a leiratkozás. Csak a „Megerősített” címekre megy levél. Címeket ne importálj hozzájárulás nélkül. Az adatkezelési tájékoztatót a ",h("a",{href:"#/admin/jogi-szovegek",style:{textDecoration:"underline"}},"Jogi szövegek"),"ben szerkesztheted (nyilvános címe: /hirlevel)."),
    h("div",{style:{...S.grid,marginBottom:24},"data-testid":"nl-counts"},
      [["Megerősített",cnt.confirmed],["Megerősítésre vár",cnt.pending],["Leiratkozott",cnt.unsubscribed],["Összes cím",cnt.confirmed+cnt.pending+cnt.unsubscribed]].map(([l,n])=>h("div",{key:l,style:S.kpi},h("div",{style:S.kpiN},NF.format(n)),h("div",{style:S.kpiL},l)))),
    h(Card,{title:"Új hírlevél",description:"Sima szöveg, üres sor új bekezdést kezd. A lábléc (leiratkozás, adatkezelés, impresszum) automatikusan bekerül."},
      h("div",{style:{display:"grid",gap:12}},
        h("label",null,h("span",{style:S.lab},"Tárgy (magyar)"),h("input",{style:S.inp,value:f.subject,maxLength:150,onChange:ev=>setF({...f,subject:ev.target.value}),"data-testid":"nl-subject"})),
        h("label",null,h("span",{style:S.lab},"Levél szövege (magyar)"),h("textarea",{style:{...S.inp,minHeight:180,fontFamily:"inherit",lineHeight:1.5},value:f.body,onChange:ev=>setF({...f,body:ev.target.value}),"data-testid":"nl-body"})),
        h("label",{style:{display:"flex",gap:8,alignItems:"center",fontSize:14}},h("input",{type:"checkbox",checked:en,onChange:ev=>{setEn(ev.target.checked);if(ev.target.checked&&!f.subjectEn.trim()&&!f.bodyEn.trim()&&shared())setTimeout(trNow,0)}}),"Angol változat is – a levélben mindkét nyelv benne van, felül Magyar / English váltógombbal (az előfizető saját nyelve van elöl)",en&&h("button",{type:"button",style:{...S.btn,marginLeft:8},onClick:trNow,disabled:!shared()||trBusy},trBusy?"Fordítás…":"Fordítás magyarból (Google)")),
        en&&h("div",{style:{display:"grid",gap:12}},h("label",null,h("span",{style:S.lab},"Subject (English)"),h("input",{style:S.inp,value:f.subjectEn,maxLength:150,onChange:ev=>setF({...f,subjectEn:ev.target.value})})),h("label",null,h("span",{style:S.lab},"Message (English)"),h("textarea",{style:{...S.inp,minHeight:140,fontFamily:"inherit"},value:f.bodyEn,onChange:ev=>setF({...f,bodyEn:ev.target.value})}))),
        h("div",{style:{display:"grid",gap:12,gridTemplateColumns:"repeat(auto-fit,minmax(220px,1fr))"}},
          h("label",null,h("span",{style:S.lab},"Link felirata (nem kötelező)"),h("input",{style:S.inp,value:f.ctaLabel,maxLength:60,placeholder:"pl. Megnézem a webshopban",onChange:ev=>setF({...f,ctaLabel:ev.target.value})})),
          h("label",null,h("span",{style:S.lab},"Link címe (https://…)"),h("input",{style:S.inp,value:f.ctaUrl,placeholder:"https://bagdikata.hu",onChange:ev=>setF({...f,ctaUrl:ev.target.value})}))),
        h("div",{style:{display:"flex",flexWrap:"wrap",gap:10,alignItems:"center"}},
          h("button",{type:"button",style:S.btn,onClick:()=>test("hu"),disabled:!shared()||!!run,"data-testid":"nl-test"},"Tesztlevél magamnak"),
          en&&h("button",{type:"button",style:S.btn,onClick:()=>test("en"),disabled:!shared()||!!run},"Teszt angolul"),
          h("button",{type:"button",style:{...S.btnD,opacity:(!shared()||run||!cnt.confirmed)?.5:1},onClick:send,disabled:!shared()||!!run||!cnt.confirmed,"data-testid":"nl-send"},"Kiküldés "+NF.format(cnt.confirmed)+" megerősített feliratkozónak"),
          run&&h("button",{type:"button",style:S.btn,onClick:()=>{stop.current=true}},"Szüneteltetés")),
        run&&data&&(()=>{let c=data.campaigns.find(x=>x.id===run.id);if(!c)return null;let done=c.sent+c.failed,pct=c.total?Math.round(done/c.total*100):0;return h("div",{role:"status"},h("div",{style:{fontSize:13,marginBottom:4}},"Küldés folyamatban: "+done+" / "+c.total+" – ne zárd be az oldalt."),h("div",{style:{height:8,background:"#e5e7eb",borderRadius:4}},h("div",{style:{height:8,borderRadius:4,width:pct+"%",background:"#4f46e5"}})))})(),
        h("p",{style:S.sub},"A tárhelyek gyakran óránként korlátozzák a kimenő levelek számát (néhol 100–300). Ha a küldés megszakad, a Folytatás gomb onnan folytatja, ahol abbamaradt – senki nem kap kétszer levelet."))),
    h(Card,{title:"Korábbi kiküldések"},
      !data||!data.campaigns.length?h("p",{style:S.sub},"Még nem küldtél hírlevelet."):
      h("div",{style:scroll},h("table",{style:{width:"100%",borderCollapse:"collapse",minWidth:620}},h("thead",null,h("tr",null,["Időpont","Tárgy","Elküldve","Sikertelen","Állapot",""].map((x,i)=>h("th",{key:i,style:S.th},x)))),
        h("tbody",null,data.campaigns.map(c=>{let[tone,lbl]=CSTAT[c.status]||["gray",c.status];return h("tr",{key:c.id},h("td",{style:S.td},fmtDate(c.createdAt)),h("td",{style:S.td},c.subject),h("td",{style:S.td},c.sent+" / "+c.total),h("td",{style:S.td},c.failed),h("td",{style:S.td},h(Badge,{tone},lbl)),
          h("td",{style:S.td},(c.status==="sending"||c.status==="paused")&&c.remaining>0&&!run&&h("span",null,h("button",{type:"button",style:S.btn,onClick:()=>runCampaign(c.id)},"Folytatás")," ",h("button",{type:"button",style:S.btn,onClick:()=>campAct("campaign_cancel",c.id)},"Leállít"))))}))))),
    h(Card,{title:"Feliratkozók",description:"A hozzájárulás időpontja és forrása a jogi megfelelés bizonyítéka – ne töröld, amíg nem kérik."},
      h("div",{style:{display:"flex",flexWrap:"wrap",gap:10,marginBottom:12,alignItems:"center"}},
        h("input",{style:{...S.inp,maxWidth:260},placeholder:"Keresés e-mail vagy név szerint",value:q,onChange:ev=>{setQ(ev.target.value);setLim(50)}}),
        h("select",{style:{...S.inp,maxWidth:200},value:sf,onChange:ev=>{setSf(ev.target.value);setLim(50)}},h("option",{value:"all"},"Minden állapot"),h("option",{value:"confirmed"},"Megerősített"),h("option",{value:"pending"},"Megerősítésre vár"),h("option",{value:"unsubscribed"},"Leiratkozott")),
        h("a",{href:window.__BK&&window.__BK.base?window.__BK.base+"api/newsletter.php?export=csv":"#",style:{...S.btn,textDecoration:"none"},"data-testid":"nl-export"},"Exportálás (CSV)")),
      !data?h("p",{style:S.sub},shared()?"Betöltés…":"–"):subs.length===0?h("p",{style:S.sub},data.subscribers.length?"Nincs találat.":"Még nincs feliratkozó. A pénztárban és a lábléc űrlapján érkeznek."):
      h("div",{style:scroll},h("table",{style:{width:"100%",borderCollapse:"collapse",minWidth:760},"data-testid":"nl-table"},h("thead",null,h("tr",null,["E-mail","Név","Állapot","Forrás","Hozzájárulás","Megerősítve",""].map((x,i)=>h("th",{key:i,style:S.th},x)))),
        h("tbody",null,subs.slice(0,lim).map(s=>{let[tone,lbl]=STAT[s.status]||["gray",s.status];return h("tr",{key:s.id,"data-testid":"nl-row"},h("td",{style:S.td},s.email),h("td",{style:S.td},s.name||"–"),h("td",{style:S.td},h(Badge,{tone},lbl)),h("td",{style:S.td},(SRC[s.source]||s.source)+(s.lang==="en"?" · EN":"")),h("td",{style:S.td},fmtDate(s.consentAt)),h("td",{style:S.td},s.status==="unsubscribed"?"leiratkozott: "+fmtDate(s.unsubscribedAt):fmtDate(s.confirmedAt)),
          h("td",{style:{...S.td,whiteSpace:"nowrap"}},s.status!=="unsubscribed"&&h("button",{type:"button",style:S.btn,onClick:()=>subAct("unsub",s)},"Leiratkoztat")," ",h("button",{type:"button",style:S.btnR,onClick:()=>subAct("delete",s)},"Töröl")))})))),
      subs.length>lim&&h("p",{style:{marginTop:12}},h("button",{type:"button",style:S.btn,onClick:()=>setLim(lim+100)},"További "+Math.min(100,subs.length-lim)+" megjelenítése"))));
}

/* ======================= NYELV ÉS PÉNZNEM ======================= */
function LanguagePage(){
  let{settings,save}=useSettings();
  let eur=settings.currency?.eurEnabled!==false;
  let[st,setSt]=N.useState(null),[key,setKey]=N.useState(""),[msg,setMsg]=N.useState(null),[busy,setBusy]=N.useState(false);
  let load=()=>{if(!shared())return;api("translate.php",{action:"status"}).then(setSt).catch(e=>setMsg({e:1,t:e.message}))};
  N.useEffect(load,[]);
  let run=(body,okText)=>{setBusy(true);setMsg(null);return api("translate.php",body).then(j=>{setBusy(false);if(j.configured!==undefined)setSt(j);setMsg({t:okText(j)});return j}).catch(e=>{setBusy(false);setMsg({e:1,t:e.message});load()})};
  return h("div",{style:{maxWidth:820}},
    h(PageHeader,{title:"Nyelv és pénznem",description:"Az euró-árak megjelenítése és az angol szövegek automatikus fordítása."}),
    h(Card,{title:"Euró (€) ár megjelenítése",description:"Kikapcsolva a webshop csak forintban mutatja az árakat: eltűnik a Ft/€ váltó és az árfolyam sor, az angol oldal is forintban számol."},
      h("label",{style:{display:"flex",gap:10,alignItems:"center",fontSize:15}},
        h("input",{type:"checkbox",checked:eur,onChange:ev=>save({...settings,currency:{eurEnabled:ev.target.checked}}),"data-testid":"eur-toggle"}),
        eur?"Az árak euróban is megjeleníthetők (Ft/€ váltó látszik)":"Csak forint – az euró ki van kapcsolva"),
      !eur&&h("p",{style:{...S.sub,marginTop:10}},"A Barion fizetésnél ilyenkor csak a forint-fizetés használható.")),
    h(Card,{title:"Automatikus angol fordítás (Google Fordító)",description:"Termékek (név, leírás), Kezdőlap és tartalom, Jogi szövegek és Hírlevél. Mentéskor a megváltozott magyar szöveg angolját a Google Fordító elkészíti, a korábbi angol szöveg helyére lép."},
      !shared()&&h(NeedShared,null),
      h("div",{style:S.info},"Szabály: ha egy magyar szöveget módosítasz, az angol párja újrafordítódik. Ha ugyanabban a mentésben te magad is átírtad az angolt, azt nem bántja. Ha az angol mező üres, kitölti. Az angol szöveget később bármikor kézzel is javíthatod – ez addig marad, amíg a magyar nem változik újra."),
      st&&h("div",{style:{display:"grid",gap:8,marginBottom:14,fontSize:14}},
        h("div",null,"Állapot: ",st.configured?h(Badge,{tone:"green"},"API kulcs megadva (…"+st.keyTail+")"):h(Badge,{tone:"amber"},"nincs API kulcs – a fordítás nem működik")),
        h("div",null,"Ebben a hónapban lefordított karakter: ",h("b",null,NF.format(st.monthChars||0)),"  ·  összesen: ",NF.format(st.totalChars||0)),
        st.lastError&&h("div",{style:S.err},"Utolsó hiba: "+st.lastError)),
      msg&&h("div",{style:msg.e?S.err:S.ok},msg.t),
      h("label",null,h("span",{style:S.lab},"Google Cloud Translation API kulcs"),
        h("input",{style:S.inp,type:"password",autoComplete:"off",value:key,placeholder:st&&st.configured?"(új kulcs beírásával cserélhető)":"AIza…",onChange:ev=>setKey(ev.target.value),"data-testid":"tr-key"})),
      h("div",{style:{display:"flex",flexWrap:"wrap",gap:10,marginTop:12}},
        h("button",{type:"button",style:S.btnD,disabled:!shared()||busy||!key.trim(),onClick:()=>run({action:"setkey",key:key.trim()},()=>"A kulcs elmentve.").then(()=>setKey("")),"data-testid":"tr-save"},"Kulcs mentése"),
        h("button",{type:"button",style:S.btn,disabled:!shared()||busy||!(st&&st.configured),onClick:()=>run({action:"test"},j=>"Működik. Próbafordítás: „Kézzel kötött sapka” → „"+j.text+"”"),"data-testid":"tr-test"},"Kapcsolat próbája"),
        h("button",{type:"button",style:S.btnR,disabled:!shared()||busy||!(st&&st.configured),onClick:()=>{if(window.confirm("Törlöd a mentett API kulcsot? Utána az automatikus fordítás leáll."))run({action:"setkey",key:""},()=>"A kulcs törölve.")}},"Kulcs törlése")),
      h("p",{style:{...S.sub,marginTop:14,lineHeight:1.6}},"A kulcsot a Google Cloud Console-ban kell létrehozni (Cloud Translation API engedélyezése, majd API kulcs). A kulcs csak a szerveren tárolódik, a böngészőbe nem kerül ki. A díj karakterenként számolódik (havi 500 000 karakterig általában ingyenes), az azonos szövegeket a rendszer nem fordítja le újra."))
  );
}


/* ======================= FRISS HÍREK ======================= */
function NewsAdminPage(){
  let{content,save}=useContent();
  let init=()=>JSON.parse(JSON.stringify(content.news||{enabled:false,title:{hu:"Friss hírek és akciók",en:"News & offers"},items:[]}));
  let[d,setD]=N.useState(init),[msg,setMsg]=N.useState(null);
  let dirty=JSON.stringify(d)!==JSON.stringify(content.news||{enabled:false,title:{hu:"Friss hírek és akciók",en:"News & offers"},items:[]});
  let setItem=(i,k,v)=>setD(x=>({...x,items:x.items.map((it,j)=>j===i?{...it,[k]:v}:it)}));
  let setPair=(i,k,l,v)=>setD(x=>({...x,items:x.items.map((it,j)=>j===i?{...it,[k]:{...it[k],[l]:v}}:it)}));
  let add=kind=>setD(x=>({...x,items:[{id:"n"+Date.now().toString(36)+Math.random().toString(36).slice(2,6),kind,date:new Date().toISOString().slice(0,10),until:"",title:{hu:"",en:""},text:{hu:"",en:""},link:""},...x.items]}));
  let del=i=>{if(window.confirm("Törlöd ezt a bejegyzést?"))setD(x=>({...x,items:x.items.filter((_,j)=>j!==i)}))};
  let doSave=()=>{let n={...d,items:d.items.filter(it=>(it.title.hu||"").trim()||(it.text.hu||"").trim())};setD(n);save({...content,news:n});setMsg(shared()?"Mentve – az angol szöveg automatikusan frissül.":"Mentve.");window.setTimeout(()=>setMsg(null),4000)};
  let two={display:"grid",gap:12,gridTemplateColumns:"repeat(auto-fit,minmax(240px,1fr))"};
  return h("div",{style:{maxWidth:900,paddingBottom:90}},
    h(PageHeader,{title:"Friss hírek és akciók",description:"A termékek alatt megjelenő szekció a nyitóoldalon."}),
    h(Card,{title:"Megjelenítés"},
      h("label",{style:{display:"flex",gap:10,alignItems:"center",fontSize:15}},h("input",{type:"checkbox",checked:!!d.enabled,onChange:ev=>setD({...d,enabled:ev.target.checked}),"data-testid":"news-toggle"}),d.enabled?"Bekapcsolva – a szekció látszik a webshopban (ha van aktív bejegyzés)":"Kikapcsolva – a szekció nem látszik"),
      h("div",{style:{...two,marginTop:14}},
        h("label",null,h("span",{style:S.lab},"Szekció címe (magyar)"),h("input",{style:S.inp,value:d.title.hu,maxLength:80,onChange:ev=>setD({...d,title:{...d.title,hu:ev.target.value}})})),
        h("label",null,h("span",{style:S.lab},"Title (English)"),h("input",{style:S.inp,value:d.title.en,maxLength:80,onChange:ev=>setD({...d,title:{...d.title,en:ev.target.value}})})))),
    h(Card,{title:"Bejegyzések ("+d.items.length+")",description:"A legújabb dátumú jelenik meg elöl. A lejárat napja után a bejegyzés magától eltűnik."},
      h("div",{style:{display:"flex",gap:10,marginBottom:14,flexWrap:"wrap"}},
        h("button",{type:"button",style:S.btnD,onClick:()=>add("sale"),"data-testid":"news-add-sale"},"+ Új akció"),
        h("button",{type:"button",style:S.btn,onClick:()=>add("news"),"data-testid":"news-add-news"},"+ Új hír")),
      !d.items.length&&h("p",{style:S.sub},"Még nincs bejegyzés."),
      d.items.map((it,i)=>h("div",{key:it.id,style:{border:"1px solid #e5e7eb",borderRadius:10,padding:14,marginBottom:12,background:"#fafafa"},"data-testid":"news-item"},
        h("div",{style:{...two,gridTemplateColumns:"repeat(auto-fit,minmax(150px,1fr))",marginBottom:10}},
          h("label",null,h("span",{style:S.lab},"Típus"),h("select",{style:S.inp,value:it.kind,onChange:ev=>setItem(i,"kind",ev.target.value)},h("option",{value:"sale"},"Akció"),h("option",{value:"news"},"Hír"))),
          h("label",null,h("span",{style:S.lab},"Dátum"),h("input",{style:S.inp,type:"date",value:it.date||"",onChange:ev=>setItem(i,"date",ev.target.value)})),
          h("label",null,h("span",{style:S.lab},"Lejár (nem kötelező)"),h("input",{style:S.inp,type:"date",value:it.until||"",onChange:ev=>setItem(i,"until",ev.target.value)}))),
        h("div",{style:two},
          h("label",null,h("span",{style:S.lab},"Cím (magyar)"),h("input",{style:S.inp,value:it.title.hu,maxLength:120,onChange:ev=>setPair(i,"title","hu",ev.target.value),"data-testid":"news-title-hu"})),
          h("label",null,h("span",{style:S.lab},"Title (English)"),h("input",{style:S.inp,value:it.title.en,maxLength:120,onChange:ev=>setPair(i,"title","en",ev.target.value)}))),
        h("div",{style:{...two,marginTop:10}},
          h("label",null,h("span",{style:S.lab},"Szöveg (magyar)"),h("textarea",{style:{...S.inp,minHeight:80,fontFamily:"inherit"},value:it.text.hu,maxLength:600,onChange:ev=>setPair(i,"text","hu",ev.target.value)})),
          h("label",null,h("span",{style:S.lab},"Text (English)"),h("textarea",{style:{...S.inp,minHeight:80,fontFamily:"inherit"},value:it.text.en,maxLength:600,onChange:ev=>setPair(i,"text","en",ev.target.value)}))),
        h("div",{style:{display:"flex",gap:12,alignItems:"end",marginTop:10,flexWrap:"wrap"}},
          h("label",{style:{flex:1,minWidth:220}},h("span",{style:S.lab},"Link (nem kötelező; https://… vagy #/termek/…)"),h("input",{style:S.inp,value:it.link||"",maxLength:300,onChange:ev=>setItem(i,"link",ev.target.value)})),
          h("button",{type:"button",style:S.btnR,onClick:()=>del(i)},"Törlés"))))),
    msg&&h("div",{style:S.ok,role:"status"},msg),
    dirty&&h("div",{style:{position:"fixed",left:0,right:0,bottom:0,background:"#fff",borderTop:"1px solid #e5e7eb",padding:"12px 24px",display:"flex",justifyContent:"flex-end",gap:10,zIndex:20}},
      h("button",{type:"button",style:S.btn,onClick:()=>setD(init())},"Elvetés"),
      h("button",{type:"button",style:S.btnD,onClick:doSave,"data-testid":"news-save"},"Mentés")),
    !dirty&&h("div",{style:{marginTop:8}},h("button",{type:"button",style:S.btnD,onClick:doSave,"data-testid":"news-save2"},"Mentés")));
}


/* ======================= ESEMÉNYEK ======================= */
function EventsAdminPage(){
  let{content,save}=useContent();
  let def={enabled:false,title:{hu:"Események",en:"Events"},items:[]};
  let init=()=>JSON.parse(JSON.stringify(content.events||def));
  let[d,setD]=N.useState(init),[msg,setMsg]=N.useState(null);
  let dirty=JSON.stringify(d)!==JSON.stringify(content.events||def);
  let setItem=(i,k,v)=>setD(x=>({...x,items:x.items.map((it,j)=>j===i?{...it,[k]:v}:it)}));
  let setPair=(i,k,l,v)=>setD(x=>({...x,items:x.items.map((it,j)=>j===i?{...it,[k]:{...it[k],[l]:v}}:it)}));
  let add=()=>setD(x=>({...x,items:[...x.items,{id:"e"+Date.now().toString(36)+Math.random().toString(36).slice(2,6),date:new Date().toISOString().slice(0,10),endDate:"",time:"",place:"",title:{hu:"",en:""},text:{hu:"",en:""},link:""}]}));
  let del=i=>{if(window.confirm("Törlöd ezt az eseményt?"))setD(x=>({...x,items:x.items.filter((_,j)=>j!==i)}))};
  let doSave=()=>{let bad=d.items.find(it=>it.link&&!/^https?:\/\//.test(it.link.trim()));if(bad){setMsg({e:1,t:"Az esemény linkje https://-sel kezdődjön."});return}
    let n={...d,items:d.items.filter(it=>(it.title.hu||"").trim()||(it.text.hu||"").trim()).map(it=>({...it,link:(it.link||"").trim()}))};setD(n);save({...content,events:n});setMsg({t:shared()?"Mentve – az angol szöveg automatikusan frissül.":"Mentve."});window.setTimeout(()=>setMsg(null),4000)};
  let two={display:"grid",gap:12,gridTemplateColumns:"repeat(auto-fit,minmax(240px,1fr))"};
  let fb=content.social&&content.social.facebookUrl;
  return h("div",{style:{maxWidth:900,paddingBottom:90}},
    h(PageHeader,{title:"Események",description:"Facebook-események megjelenítése a nyitóoldalon, a friss hírek alatt."}),
    h(Card,{title:"Megjelenítés"},
      h("label",{style:{display:"flex",gap:10,alignItems:"center",fontSize:15}},h("input",{type:"checkbox",checked:!!d.enabled,onChange:ev=>setD({...d,enabled:ev.target.checked}),"data-testid":"events-toggle"}),d.enabled?"Bekapcsolva – az Események blokk látszik a webshopban (ha van közelgő esemény)":"Kikapcsolva – a blokk nem látszik"),
      h("div",{style:{...two,marginTop:14}},
        h("label",null,h("span",{style:S.lab},"Blokk címe (magyar)"),h("input",{style:S.inp,value:d.title.hu,maxLength:80,onChange:ev=>setD({...d,title:{...d.title,hu:ev.target.value}})})),
        h("label",null,h("span",{style:S.lab},"Title (English)"),h("input",{style:S.inp,value:d.title.en,maxLength:80,onChange:ev=>setD({...d,title:{...d.title,en:ev.target.value}})})))),
    h("div",{style:S.info},"Facebook nem engedi automatikusan lehúzni az eseményeket (ehhez külön Meta-alkalmazás és jóváhagyás kellene), ezért az eseményt itt kell felvenni, a Facebook-esemény linkjével. A webshopban a kártyán „Facebook esemény megnyitása” gomb vezet az eseményre."+(fb?" Facebook-oldalad: "+fb:"")),
    h(Card,{title:"Események ("+d.items.length+")",description:"Időrendben jelennek meg. Az esemény napja (vagy a vége dátum) után magától eltűnik."},
      h("div",{style:{marginBottom:14}},h("button",{type:"button",style:S.btnD,onClick:add,"data-testid":"events-add"},"+ Új esemény")),
      !d.items.length&&h("p",{style:S.sub},"Még nincs esemény."),
      d.items.map((it,i)=>h("div",{key:it.id,style:{border:"1px solid #e5e7eb",borderRadius:10,padding:14,marginBottom:12,background:"#fafafa"},"data-testid":"event-item"},
        h("div",{style:{...two,gridTemplateColumns:"repeat(auto-fit,minmax(150px,1fr))",marginBottom:10}},
          h("label",null,h("span",{style:S.lab},"Kezdő nap"),h("input",{style:S.inp,type:"date",value:it.date||"",onChange:ev=>setItem(i,"date",ev.target.value)})),
          h("label",null,h("span",{style:S.lab},"Utolsó nap (nem kötelező)"),h("input",{style:S.inp,type:"date",value:it.endDate||"",onChange:ev=>setItem(i,"endDate",ev.target.value)})),
          h("label",null,h("span",{style:S.lab},"Időpont (pl. 10:00–16:00)"),h("input",{style:S.inp,value:it.time||"",maxLength:40,onChange:ev=>setItem(i,"time",ev.target.value)})),
          h("label",null,h("span",{style:S.lab},"Helyszín"),h("input",{style:S.inp,value:it.place||"",maxLength:120,onChange:ev=>setItem(i,"place",ev.target.value)}))),
        h("div",{style:two},
          h("label",null,h("span",{style:S.lab},"Cím (magyar)"),h("input",{style:S.inp,value:it.title.hu,maxLength:120,onChange:ev=>setPair(i,"title","hu",ev.target.value),"data-testid":"event-title-hu"})),
          h("label",null,h("span",{style:S.lab},"Title (English)"),h("input",{style:S.inp,value:it.title.en,maxLength:120,onChange:ev=>setPair(i,"title","en",ev.target.value)}))),
        h("div",{style:{...two,marginTop:10}},
          h("label",null,h("span",{style:S.lab},"Leírás (magyar)"),h("textarea",{style:{...S.inp,minHeight:70,fontFamily:"inherit"},value:it.text.hu,maxLength:600,onChange:ev=>setPair(i,"text","hu",ev.target.value)})),
          h("label",null,h("span",{style:S.lab},"Description (English)"),h("textarea",{style:{...S.inp,minHeight:70,fontFamily:"inherit"},value:it.text.en,maxLength:600,onChange:ev=>setPair(i,"text","en",ev.target.value)}))),
        h("div",{style:{display:"flex",gap:12,alignItems:"end",marginTop:10,flexWrap:"wrap"}},
          h("label",{style:{flex:1,minWidth:220}},h("span",{style:S.lab},"Facebook-esemény linkje (https://www.facebook.com/events/…)"),h("input",{style:S.inp,value:it.link||"",maxLength:300,onChange:ev=>setItem(i,"link",ev.target.value),"data-testid":"event-link"})),
          h("button",{type:"button",style:S.btnR,onClick:()=>del(i)},"Törlés"))))),
    msg&&h("div",{style:msg.e?S.err||S.ok:S.ok,role:"status"},msg.t),
    dirty&&h("div",{style:{position:"fixed",left:0,right:0,bottom:0,background:"#fff",borderTop:"1px solid #e5e7eb",padding:"12px 24px",display:"flex",justifyContent:"flex-end",gap:10,zIndex:20}},
      h("button",{type:"button",style:S.btn,onClick:()=>setD(init())},"Elvetés"),
      h("button",{type:"button",style:S.btnD,onClick:doSave,"data-testid":"events-save"},"Mentés")),
    !dirty&&h("div",{style:{marginTop:8}},h("button",{type:"button",style:S.btnD,onClick:doSave,"data-testid":"events-save2"},"Mentés")));
}

/* ======================= FRISSÍTÉSI ELŐZMÉNYEK ======================= */
function UpdateHistory(){
  let list=Array.isArray(window.__BK_CHANGELOG)?window.__BK_CHANGELOG:[];
  return h("div",{"data-testid":"updates-tab"},
    h("div",{style:S.info},"Jelenlegi verzió: ",h("b",null,"v"+(window.__BKV||"–")),". A verziószám a legfelső bejegyzésből jön, ez látszik az oldal alján és az adminban is. Így ellenőrizheted, mi ment ki élesbe."),
    h("section",{style:S.card},h("div",{style:{overflowX:"auto"}},h("table",{style:{width:"100%",borderCollapse:"collapse"}},
      h("thead",null,h("tr",null,h("th",{style:S.th},"Dátum"),h("th",{style:S.th},"Mi módosult"),h("th",{style:S.th},"Verzió"))),
      h("tbody",null,list.map((x,i)=>h("tr",{key:x.v},
        h("td",{style:{...S.td,whiteSpace:"nowrap",verticalAlign:"top"}},x.d),
        h("td",{style:S.td},h("ul",{style:{margin:0,paddingLeft:18}},x.c.map((c,j)=>h("li",{key:j},c)))),
        h("td",{style:{...S.td,verticalAlign:"top",whiteSpace:"nowrap"}},h(Badge,{tone:i===0?"green":"gray"},"v"+x.v+(i===0?" (jelenlegi)":""))))))))));
}
function HistoryTabs({Audit}){
  let[tab,setTab]=N.useState(()=>{try{return sessionStorage.getItem("bk_histtab")||"admin"}catch{return"admin"}});
  let pick=t=>{setTab(t);try{sessionStorage.setItem("bk_histtab",t)}catch{}};
  let tb=(id,label)=>h("button",{type:"button",role:"tab","aria-selected":tab===id,onClick:()=>pick(id),"data-testid":"tab-"+id,style:{border:"none",background:"none",padding:"10px 16px",fontSize:14,fontWeight:600,cursor:"pointer",color:tab===id?"#111827":"#6b7280",borderBottom:tab===id?"2px solid #4f46e5":"2px solid transparent",marginBottom:-1}},label);
  return h("div",null,
    h("div",{role:"tablist",style:{display:"flex",gap:4,borderBottom:"1px solid #e5e7eb",marginBottom:20}},tb("admin","Admin előzmények"),tb("updates","Frissítési előzmények")),
    tab==="admin"?h(Audit,null):h(UpdateHistory,null));
}


/* ======================= NAPI JELENTÉSEK (több, független címzett) ======================= */
var SEC_LABELS={traffic:"Forgalom",topProducts:"Legjobb termékek",orders:"Rendelések",lowStock:"Alacsony készlet"};
function DailyReports(){
  let{settings,save}=useSettings();
  let legacy=settings.notifications.dailyReport;
  let stored=settings.notifications.dailyReports;
  let list=Array.isArray(stored)?stored:(legacy&&legacy.enabled?(legacy.recipients||[]).map((e,i)=>({id:"legacy"+i,email:e,enabled:true,time:legacy.time||"07:00",sections:{...legacy.sections},createdAt:""})):[]);
  let[nw,setNw]=N.useState({email:"",time:"07:00",sections:{traffic:true,topProducts:true,orders:true,lowStock:true}});
  let[msg,setMsg]=N.useState(null),[st,setSt]=N.useState({}),[busy,setBusy]=N.useState("");
  let loadSt=()=>{if(!shared())return;api("reports.php",{action:"status"}).then(j=>setSt(j.last||{})).catch(()=>{})};
  N.useEffect(loadSt,[Array.isArray(stored)?stored.length:-1]);
  let commit=(next,text)=>{save({...settings,notifications:{...settings.notifications,dailyReports:next}});if(text)setMsg({t:text});window.setTimeout(()=>setMsg(null),5000)};
  let add=()=>{let e=nw.email.trim().toLowerCase();if(!/^\S+@\S+\.\S+$/.test(e))return setMsg({e:1,t:"Adj meg érvényes e-mail-címet."});
    if(list.some(r=>r.email===e&&r.time===nw.time))return setMsg({e:1,t:"Erre a címre már van azonos időpontú jelentés."});
    if(list.length>=20)return setMsg({e:1,t:"Legfeljebb 20 jelentés állítható be."});
    if(!Object.values(nw.sections).some(Boolean))return setMsg({e:1,t:"Jelölj ki legalább egy tartalmi részt."});
    commit([...list,{id:"r"+Date.now().toString(36)+Math.random().toString(36).slice(2,6),email:e,enabled:true,time:nw.time,sections:{...nw.sections},createdAt:new Date().toISOString()}],"Jelentés létrehozva: "+e+", minden nap "+nw.time+"-kor.");
    setNw({...nw,email:""})};
  let del=r=>{if(window.confirm("Törlöd ezt a napi jelentést?\n"+r.email+" – "+r.time))commit(list.filter(x=>x.id!==r.id),"Jelentés törölve: "+r.email)};
  let toggle=r=>commit(list.map(x=>x.id===r.id?{...x,enabled:!x.enabled}:x),(r.enabled?"Szüneteltetve: ":"Bekapcsolva: ")+r.email);
  let sendTest=r=>{setBusy(r.id);setMsg(null);api("reports.php",{action:"send",id:r.id}).then(j=>{setBusy("");setMsg({t:"Teszt jelentés elküldve: "+j.to});loadSt()}).catch(e=>{setBusy("");setMsg({e:1,t:e.message})})};
  let fmtLast=l=>l&&l.sentAt?(l.ok?"Utoljára elküldve: ":"Sikertelen küldés: ")+fmtDate(l.sentAt):"Még nem ment ki";
  return h("section",{style:S.card,"data-testid":"daily-reports"},
    h("header",{style:S.head},h("h2",{style:S.h2},"Napi jelentések e-mailben"),h("p",{style:S.sub},"Minden reggel összefoglaló az előző napról. Több jelentés is beállítható, akár több, egymástól független e-mail-címre, eltérő időponttal és tartalommal.")),
    h("div",{style:S.body},
      msg&&h("div",{style:msg.e?S.err:S.ok,role:"status"},msg.t),
      !list.length&&h("p",{style:S.sub},"Még nincs beállított napi jelentés."),
      list.map(r=>h("div",{key:r.id,"data-testid":"report-row",style:{border:"1px solid #e5e7eb",borderRadius:10,padding:"12px 14px",marginBottom:10,background:r.enabled?"#fff":"#f9fafb",display:"flex",flexWrap:"wrap",gap:12,alignItems:"center"}},
        h("div",{style:{flex:1,minWidth:240}},
          h("div",{style:{fontWeight:600,fontSize:15,wordBreak:"break-all"}},r.email," ",h(Badge,{tone:r.enabled?"green":"gray"},r.enabled?"aktív":"szünetel")),
          h("div",{style:{fontSize:13,color:"#4b5563",marginTop:3}},"Minden nap "+r.time+"-kor · "+Object.keys(SEC_LABELS).filter(k=>r.sections[k]).map(k=>SEC_LABELS[k]).join(", ")),
          h("div",{style:{fontSize:12,color:"#6b7280",marginTop:2}},fmtLast(st[r.id])+(r.createdAt?" · létrehozva: "+fmtDate(r.createdAt):""))),
        h("div",{style:{display:"flex",gap:8,flexWrap:"wrap"}},
          h("button",{type:"button",style:S.btn,disabled:!shared()||busy===r.id||!Array.isArray(stored),onClick:()=>sendTest(r),title:Array.isArray(stored)?"":"Előbb hozz létre vagy módosíts egy jelentést, hogy a lista elmentődjön"},busy===r.id?"Küldés…":"Teszt küldése"),
          h("button",{type:"button",style:S.btn,onClick:()=>toggle(r)},r.enabled?"Szüneteltet":"Bekapcsol"),
          h("button",{type:"button",style:S.btnR,onClick:()=>del(r),"data-testid":"report-del"},"Törlés")))),
      h("div",{style:{borderTop:"1px solid #e5e7eb",marginTop:16,paddingTop:16}},
        h("div",{style:{fontWeight:600,marginBottom:10}},"Új jelentés létrehozása"),
        h("div",{style:{display:"grid",gap:12,gridTemplateColumns:"repeat(auto-fit,minmax(220px,1fr))"}},
          h("label",null,h("span",{style:S.lab},"E-mail-cím"),h("input",{style:S.inp,type:"email",value:nw.email,placeholder:"pelda@email.hu",onChange:ev=>setNw({...nw,email:ev.target.value}),"data-testid":"report-email"})),
          h("label",null,h("span",{style:S.lab},"Küldés ideje (naponta)"),h("input",{style:S.inp,type:"time",value:nw.time,onChange:ev=>setNw({...nw,time:ev.target.value||"07:00"})}))),
        h("div",{style:{display:"flex",flexWrap:"wrap",gap:16,margin:"12px 0"}},Object.keys(SEC_LABELS).map(k=>h("label",{key:k,style:{display:"flex",gap:6,alignItems:"center",fontSize:14}},h("input",{type:"checkbox",checked:!!nw.sections[k],onChange:ev=>setNw({...nw,sections:{...nw.sections,[k]:ev.target.checked}})}),SEC_LABELS[k]))),
        h("button",{type:"button",style:S.btnD,onClick:add,"data-testid":"report-add"},"Jelentés létrehozása")),
      !shared()&&h("div",{style:{...S.warn,marginTop:14}},"A tényleges kiküldéshez az éles, szerveres működés és a cPanel időzített feladata (cron) szükséges.")));
}

export{DailyReports,HistoryTabs,NewsAdminPage as AdminNewsPage,EventsAdminPage as AdminEventsPage,LanguagePage as AdminLanguagePage,CouponsPage as AdminCouponsPage,NewsletterPage as AdminNewsletterPage};
