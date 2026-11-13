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

/* ---------------- Jogi szövegek szerkesztése ---------------- */
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

/* ---------------- Elállások listája ---------------- */
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
