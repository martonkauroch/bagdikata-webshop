import{$ as e,Z as t,i as n,l as cx}from"./createLucideIcon-Bc0h7bbI.js";
import{A as useContent,j as useStore,LinkC}from"./index-0f-fFOYT.js";
import{t as PageHeader}from"./PageHeader-BYypCJGs.js";
import{LEGAL_DEFAULTS,LEGAL_TITLES}from"./StorePages-leg1.js";
var N=e(t(),1),P=n();
var h=(type,props,...kids)=>{let{key:k,...rest}=props||{};if(kids.length===1)rest.children=kids[0];else if(kids.length>1)rest.children=kids;return k===void 0?P.jsx(type,rest):P.jsx(type,rest,k)};
var KINDS=["terms","privacy","shipping","imprint","newsletter"];
var clip=s=>String(s||"").replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g,"").slice(0,60000);
var holes=s=>(String(s||"").match(/\[[^\]\n]{2,}\]/g)||[]).length;

function Card({title,description,children}){
  return h("section",{className:"rounded-xl border border-gray-200 bg-white shadow-sm"},
    h("header",{className:"border-b border-gray-200 px-5 py-4"},h("h2",{className:"font-sans text-base font-semibold"},title),description&&h("p",{className:"mt-1 text-sm text-gray-500"},description)),
    h("div",{className:"p-5"},children));
}

var MEASURE_CFG={hu:{stat:"- **Statisztika:**",newStat:"- **Saját látogatottsági statisztika",newRec:"- Látogatottsági statisztika:",cookieStart:"A webshop működéséhez szükséges tárolás",newCookie:"A webshop a böngésződben sütiket",ga:"**Google Analytics.**",turnB:"- Robotszűrés:",turnP:"**Robotszűrés (Cloudflare Turnstile).**",eu:"**Adattovábbítás az EU-n kívülre.**",retNew:"Statisztika és biztonság:",live:"az adminisztrációs felület élő nézetéhez",fix:[["Csak összesített számokat őrzünk meg, egyes látogatókhoz nem köthetőket.","Csak összesített számokat őrzünk meg, egyes látogatókhoz nem köthetőket; az adminisztrációs felület élő nézetéhez emellett az utolsó legfeljebb 80 esemény (időpont, esemény típusa, megnyitott oldal vagy termék) névtelen listáját is tároljuk, legfeljebb 2 óráig."],["- saját statisztika: az aznapi látogató-lenyomat a nap végéig, az összesített napi adatok 120 napig;","- saját statisztika: az aznapi látogató-lenyomat a nap végéig, az élő nézet eseménylistája legfeljebb 2 óráig, az összesített napi adatok 120 napig;"]]},en:{stat:"- **Statistics:**",newStat:"- **Own visitor statistics",newRec:"- Visitor statistics:",cookieStart:"Storage needed for the shop to work",newCookie:"The shop uses cookies and local storage",ga:"**Google Analytics.**",turnB:"- Bot protection:",turnP:"**Bot protection (Cloudflare Turnstile).**",eu:"**Transfers outside the EU.**",retNew:"Statistics and security:",live:"for the live view of the admin interface",fix:[["Only aggregated figures are kept, which cannot be linked to individual visitors.","Only aggregated figures are kept, which cannot be linked to individual visitors; for the live view of the admin interface we also keep an anonymous list of the last 80 events at most (time, type of event, page or product opened), for up to 2 hours."],["- own statistics: the day's visitor hash until the end of the day, aggregated daily figures for 120 days;","- own statistics: the day's visitor hash until the end of the day, the live view's event list for up to 2 hours, aggregated daily figures for 120 days;"]]}};
function measureMerge(text,def,lang){
  let c=MEASURE_CFG[lang],L=String(text||"").split("\n"),D=String(def||"").split("\n"),done=[];
  let sec=(A,n)=>{let a=A.findIndex(x=>new RegExp("^##\\s*"+n+"\\.").test(x));if(a<0)return null;let b=A.findIndex((x,i)=>i>a&&/^##\s*\d+\./.test(x));return[a,b<0?A.length:b]};
  let line=(A,pre)=>A.findIndex(x=>x.startsWith(pre));
  let endOf=(A,r)=>{let e=r[1];while(e>r[0]+1&&A[e-1].trim()==="")e--;return e};
  let ins=(at,arr)=>{L.splice(at,0,...arr)};
  let has=pre=>L.some(x=>x.startsWith(pre));
  if(!has(c.newStat)){let di=line(D,c.newStat),blk=di<0?[]:D.slice(di,di+4);if(blk.length){let ui=line(L,c.stat);if(ui>=0){L.splice(ui,1,...blk)}else{let r=sec(L,2);if(r)ins(endOf(L,r),blk)}done.push("statisztika")}}
  if(!has(c.newRec)){let r=sec(L,3),dr=sec(D,3);if(r&&dr){let di=line(D,c.newRec),nb=[];for(let i=di;i>=0&&i<dr[1]&&D[i].startsWith("- ");i++)nb.push(D[i]);if(!has(c.turnB)){let tb=line(D,c.turnB);tb>=0&&nb.unshift(D[tb])}let last=-1;for(let i=r[0]+1;i<r[1];i++)if(L[i].startsWith("- "))last=i;ins(last>=0?last+1:r[0]+1,nb);let r2=sec(L,3),e=endOf(L,r2),add=[];if(!has(c.turnP)){let tp=line(D,c.turnP);tp>=0&&add.push("",D[tp])}if(!has(c.eu)){let ep=line(D,c.eu);ep>=0&&add.push("",D[ep])}add.length&&ins(e,add);done.push("címzettek")}}
  if(!has(c.newCookie)){let r=sec(L,4),dr=sec(D,4);if(r&&dr){let gi=line(D,c.ga),blk=D.slice(dr[0]+1,gi>0?gi:dr[1]);while(blk.length&&blk[0].trim()==="")blk.shift();while(blk.length&&blk[blk.length-1].trim()==="")blk.pop();let ui=L.findIndex((x,i)=>i>r[0]&&i<r[1]&&x.startsWith(c.cookieStart));if(ui>=0)L.splice(ui,1,...blk);else ins(r[0]+1,blk.concat([""]));done.push("sütik")}}
  if(!has(c.ga)){let r=sec(L,4),gp=line(D,c.ga);if(r&&gp>=0){ins(endOf(L,r),["",D[gp]]);done.push("Google Analytics")}}
  if(!has(c.retNew)){let r=sec(L,5),dr=sec(D,5);if(r&&dr){let ri=line(D,c.retNew),blk=D.slice(ri,endOf(D,dr));ri>=0&&(ins(endOf(L,r),[""].concat(blk)),done.push("megőrzés"))}}
  let T=L.join("\n");if(!T.includes(c.live)){let n=0;for(let[o,w]of c.fix)if(T.includes(o)){T=T.split(o).join(w);n++}n&&done.push("élő nézet")}
  return{text:T,done};
}
function AdminLegalPage(){
  let{content,save}=useContent();
  let initial=()=>{let o={};for(let k of KINDS){let st=(content.legal||{})[k];o[k]={hu:st&&typeof st.hu==="string"&&st.hu.trim()?st.hu:LEGAL_DEFAULTS[k].hu,en:st&&typeof st.en==="string"?st.en:LEGAL_DEFAULTS[k].en}}return o};
  let[draft,setDraft]=N.useState(initial),[msg,setMsg]=N.useState(null),[errs,setErrs]=N.useState({});
  let dirty=N.useMemo(()=>JSON.stringify(draft)!==JSON.stringify(initial()),[draft,content.legal]);
  let set=(k,l,v)=>setDraft(d=>({...d,[k]:{...d[k],[l]:v}}));
  let reset=k=>{if(window.confirm("Visszaállítod az alapszöveget? A szerkesztett szöveg elvész (mentésig még visszavonható az Elvetéssel)."))setDraft(d=>({...d,[k]:{...LEGAL_DEFAULTS[k]}}))};
  let doSave=()=>{let e={};for(let k of KINDS)if(draft[k].hu.trim().length<50)e[k]="A magyar szöveg kötelező (legalább 50 karakter).";
    setErrs(e);if(Object.keys(e).length)return;
    let legal={};for(let k of KINDS)legal[k]={hu:clip(draft[k].hu),en:clip(draft[k].en)};
    let old=initial(),items=[];for(let k of KINDS)items.push({hu:legal[k].hu,en:legal[k].en,oldHu:old[k].hu,oldEn:old[k].en});
    setMsg("Mentés és angol fordítás…");
    (window.__bkTrItems?window.__bkTrItems(items):Promise.resolve(items.map(i=>i.en))).then(res=>{KINDS.forEach((k,i)=>{if(res[i]&&res[i]!==legal[k].en)legal[k].en=res[i]});setDraft(d=>{let o={...d};KINDS.forEach(k=>{o[k]={...o[k],en:legal[k].en}});return o});save({...content,legal});let er=window.__bkTrErr&&window.__bkTrErr();setMsg(er?"Mentve, de az angol fordítás nem sikerült: "+er:"Mentve – a szövegek megjelentek a webshop oldalain.");window.setTimeout(()=>setMsg(null),er?9000:4000)})};
  let total=KINDS.reduce((a,k)=>a+holes(draft[k].hu)+holes(draft[k].en),0);
  return h("div",{className:"pb-24"},
    h(PageHeader,{title:"Jogi szövegek",description:"ÁSZF, adatkezelési tájékoztató, szállítási feltételek és impresszum. Az alapszövegek előre ki vannak töltve (vázlat), szerkeszthetők. Élesítés előtt jogásszal át kell nézetni őket."}),
    h("div",{className:"space-y-6"},
      total>0?h("p",{className:"rounded-lg bg-amber-50 p-3 text-sm text-amber-900",role:"status"},"Még "+total+" kitöltendő [szögletes zárójeles] rész van a szövegekben (cégnév, cím, adószám, békéltető testület stb.). Élesítés előtt ezeket ki kell tölteni."):h("p",{className:"rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900"},"Nincs kitöltetlen [szögletes zárójeles] rész."),
      KINDS.map(k=>h(Card,{key:k,title:LEGAL_TITLES[k].hu,description:"Fejlécek: sor elején „## ”, felsorolás: „- ”. Üres sor új bekezdést kezd. Csak szöveg menthető (HTML nem)."},
        h("div",{className:"space-y-4"},
          k==="privacy"&&(!draft[k].hu.includes(MEASURE_CFG.hu.newStat)||!draft[k].hu.includes(MEASURE_CFG.hu.live))&&LEGAL_DEFAULTS.privacy.hu.includes(MEASURE_CFG.hu.newStat)&&h("div",{"data-testid":"measure-merge",style:{border:"1px solid #fcd34d",background:"#fffbeb",borderRadius:10,padding:"12px 14px",fontSize:14,color:"#78350f"}},
            h("p",{style:{margin:0}},h("strong",null,"Frissült az adatkezelési tájékoztató a mérések miatt. "),"A saját mentett szövegedből még hiányoznak a statisztikáról, a Google Analyticsről, a sütikről, a címzettekről és a megőrzési időkről szóló részek. A gomb ezeket a megfelelő fejezetekbe illeszti (magyarul és angolul), a saját kitöltéseid megmaradnak. Mentés előtt átnézheted."),
            h("button",{type:"button","data-testid":"measure-merge-btn",style:{marginTop:10,background:"#231f20",color:"#fff",borderRadius:8,padding:"8px 14px",fontSize:14},onClick:()=>{let H=measureMerge(draft[k].hu,LEGAL_DEFAULTS.privacy.hu,"hu"),E=draft[k].en&&draft[k].en.trim()?measureMerge(draft[k].en,LEGAL_DEFAULTS.privacy.en,"en"):{text:LEGAL_DEFAULTS.privacy.en,done:["teljes angol alapszöveg"]};setDraft(d=>({...d,[k]:{hu:H.text,en:E.text}}));setMsg("Beillesztve ("+(H.done.join(", ")||"nincs teendő")+"). Nézd át a szöveget, majd kattints a Mentés gombra.");window.setTimeout(()=>setMsg(null),12000)}},"Mérési változások beillesztése")),
          h("div",{className:"grid gap-4 lg:grid-cols-2"},
            h("label",{className:"block"},h("span",{className:"label"},"Magyar (kötelező) – kitöltendő: "+holes(draft[k].hu)),
              h("textarea",{className:"input",style:{minHeight:"22rem",fontFamily:"inherit",lineHeight:1.5},value:draft[k].hu,onChange:ev=>set(k,"hu",ev.target.value)}),
              errs[k]&&h("span",{className:"field-error",role:"alert"},errs[k])),
            h("label",{className:"block"},h("span",{className:"label"},"English (ha üres, a magyar szöveg jelenik meg) – kitöltendő: "+holes(draft[k].en)),
              h("textarea",{className:"input",style:{minHeight:"22rem",fontFamily:"inherit",lineHeight:1.5},value:draft[k].en,onChange:ev=>set(k,"en",ev.target.value)}))),
          h("div",{className:"flex flex-wrap gap-2"},h("button",{type:"button",className:"btn-secondary px-3 py-2",onClick:()=>reset(k)},"Alapszöveg visszaállítása"))))),
      h("p",{className:"text-sm text-gray-500"},"A szövegek a webshop láblécében lévő linkekről érhetők el: /aszf, /adatvedelem, /szallitas, /impresszum, /hirlevel.")),
    h("div",{className:cx("fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur transition lg:left-64",dirty||msg?"translate-y-0":"translate-y-full")},
      h("div",{className:"mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 sm:px-2 lg:px-4"},
        h("p",{className:cx("text-sm",msg?"text-emerald-700":"text-gray-600"),role:"status"},msg||"Nem mentett változtatások."),
        dirty&&h("div",{className:"flex gap-2"},h("button",{onClick:()=>{setDraft(initial());setErrs({})},className:"btn-secondary"},"Elvetés"),h("button",{onClick:doSave,className:"btn-admin"},"Mentés")))));
}

var WKEY="bagdikata:withdrawals:v1";
function loadW(){let b=window.__BK;if(b&&b.shared)return Array.isArray(b.data.withdrawals)?b.data.withdrawals:[];try{let v=JSON.parse(localStorage.getItem(WKEY)||"[]");return Array.isArray(v)?v:[]}catch{return[]}}
function AdminWithdrawalsPage(){
  let[list,setList]=N.useState(loadW),{orders}=useStore(),[filter,setFilter]=N.useState("all");
  N.useEffect(()=>{let b=window.__BK,f=()=>setList(loadW()),sync=()=>{if(b&&b.shared)b.reload().then(f)};sync();let id=b&&b.shared?window.setInterval(sync,20000):null;window.addEventListener("storage",f);window.addEventListener("focus",sync);return()=>{if(id)window.clearInterval(id);window.removeEventListener("storage",f);window.removeEventListener("focus",sync)}},[]);
  let update=(id,patch)=>{let next=loadW().map(x=>x.id===id?{...x,...patch}:x);{let b=window.__BK;if(b&&b.shared)b.set(WKEY,next);else try{localStorage.setItem(WKEY,JSON.stringify(next))}catch{}}setList(next)};
  let shown=list.filter(x=>filter==="all"||x.status===filter);
  let newCount=list.filter(x=>x.status==="new").length;
  let fmt=iso=>new Date(iso).toLocaleString("hu-HU",{timeZone:"Europe/Budapest",dateStyle:"medium",timeStyle:"short"});
  return h("div",{className:"pb-24"},
    h(PageHeader,{title:"Elállások",description:"A vásárlók „Elállás a szerződéstől” űrlapon küldött nyilatkozatai. A visszaigazoló e-mail automatikusan kimegy a vásárlónak (a tesztverzióban a Kimenő levelek listába kerül)."}),
    h("div",{className:"space-y-4"},
      h("div",{className:"flex flex-wrap items-center gap-2"},
        [["all","Mind ("+list.length+")"],["new","Új ("+newCount+")"],["done","Feldolgozva ("+(list.length-newCount)+")"]].map(([k,l])=>h("button",{key:k,type:"button",onClick:()=>setFilter(k),className:cx("btn-secondary px-3 py-2",filter===k&&"ring-2 ring-gray-900")},l))),
      shown.length===0?h("p",{className:"rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500"},"Még nincs elállási nyilatkozat."):
      h("ul",{className:"space-y-3"},shown.map(x=>{let o=(orders||[]).find(o=>String(o.orderNumber).toUpperCase()===x.orderNumber);
        return h("li",{key:x.id,className:"rounded-xl border border-gray-200 bg-white p-4 shadow-sm"},
          h("div",{className:"flex flex-wrap items-start justify-between gap-3"},
            h("div",{className:"min-w-0"},
              h("p",{className:"text-sm font-semibold"},x.id," · ",h("span",{className:x.status==="new"?"text-amber-700":"text-emerald-700"},x.status==="new"?"Új":"Feldolgozva")),
              h("p",{className:"text-xs text-gray-500"},fmt(x.createdAt)," (Europe/Budapest)")),
            h("div",{className:"flex gap-2"},
              h("button",{type:"button",className:"btn-secondary px-3 py-2",onClick:()=>update(x.id,{status:x.status==="new"?"done":"new"})},x.status==="new"?"Megjelölés feldolgozottként":"Visszaállítás újra"))),
          h("dl",{className:"mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2"},
            h("div",{},h("dt",{className:"text-xs text-gray-500"},"Név"),h("dd",{style:{overflowWrap:"anywhere"}},x.name)),
            h("div",{},h("dt",{className:"text-xs text-gray-500"},"E-mail"),h("dd",{style:{overflowWrap:"anywhere"}},x.email)),
            h("div",{},h("dt",{className:"text-xs text-gray-500"},"Rendelésszám"),h("dd",{},x.orderNumber," ",o?h(LinkC,{to:"/admin/rendelesek?rendeles="+encodeURIComponent(o.id),className:"text-blue-700 underline"},"megnyitás"):null)),
            h("div",{},h("dt",{className:"text-xs text-gray-500"},"Azonosítás"),h("dd",{className:x.matched?"text-emerald-700":"text-amber-700"},x.matched?"A rendelésszám és az e-mail egyezik":"Nem azonosítható automatikusan – ellenőrizd")),
            h("div",{},h("dt",{className:"text-xs text-gray-500"},"Hatály"),h("dd",{},x.scope==="all"?"A teljes rendelés":"Egyes termékek")),
            x.note&&h("div",{className:"sm:col-span-2"},h("dt",{className:"text-xs text-gray-500"},"Megjegyzés"),h("dd",{style:{overflowWrap:"anywhere",whiteSpace:"pre-wrap"}},x.note))))}))));
}
export{AdminLegalPage,AdminWithdrawalsPage};
