import{$ as e,Z as t,i as n}from"./createLucideIcon-Bc0h7bbI.js";
import{t as PageHeader}from"./PageHeader-BYypCJGs.js";
var N=e(t(),1),P=n();
var h=(type,props,...kids)=>{let{key:k,...rest}=props||{};if(kids.length===1)rest.children=kids[0];else if(kids.length>1)rest.children=kids;return k===void 0?P.jsx(type,rest):P.jsx(type,rest,k)};
var shared=()=>!!(window.__BK&&window.__BK.shared&&window.__BK.admin);
var api=(path,body)=>fetch(window.__BK.base+"api/"+path,{method:"POST",credentials:"same-origin",headers:{"X-BK":"1","Content-Type":"application/json"},body:JSON.stringify(body)}).then(r=>r.json().catch(()=>({})).then(j=>{if(!r.ok||!j||!j.ok)throw new Error(j&&j.error||"Hiba ("+r.status+")");return j}));
var up=(file)=>fetch(window.__BK.base+"api/upload.php",{method:"POST",credentials:"same-origin",headers:{"X-BK":"1","Content-Type":file.type||"application/octet-stream","X-Name":encodeURIComponent(file.name||"")},body:file}).then(r=>r.json().catch(()=>({})).then(j=>{if(!r.ok||!j.id)throw new Error(j&&j.error||(file.name+": a feltöltés nem sikerült."));return j}));
var src=id=>window.__BK.base+"api/img.php?id="+encodeURIComponent(id)+"&v=3";
var S={
  card:{background:"#fff",border:"1px solid #e5e7eb",borderRadius:12,boxShadow:"0 1px 2px rgba(0,0,0,.05)",marginBottom:24},
  head:{borderBottom:"1px solid #e5e7eb",padding:"14px 20px"},body:{padding:20},
  h2:{fontSize:16,fontWeight:600,margin:0},sub:{fontSize:13,color:"#6b7280",margin:"4px 0 0"},
  btn:{border:"1px solid #d1d5db",background:"#fff",borderRadius:8,padding:"6px 12px",fontSize:13,fontWeight:500,cursor:"pointer",color:"#111827"},
  btnD:{border:"1px solid #111827",background:"#111827",color:"#fff",borderRadius:8,padding:"8px 16px",fontSize:14,fontWeight:500,cursor:"pointer"},
  btnR:{border:"1px solid #fca5a5",background:"#fff",color:"#b91c1c",borderRadius:8,padding:"6px 12px",fontSize:13,fontWeight:500,cursor:"pointer"},
  inp:{width:"100%",border:"1px solid #d1d5db",borderRadius:8,padding:"8px 10px",fontSize:14,background:"#fff",boxSizing:"border-box"},
  err:{background:"#fef2f2",color:"#b91c1c",border:"1px solid #fecaca",borderRadius:8,padding:"8px 12px",fontSize:13,margin:"0 0 12px"},
  ok:{background:"#ecfdf5",color:"#047857",border:"1px solid #a7f3d0",borderRadius:8,padding:"8px 12px",fontSize:13,margin:"0 0 12px"},
  warn:{background:"#fffbeb",color:"#78350f",border:"1px solid #fde68a",borderRadius:8,padding:"10px 14px",fontSize:13,lineHeight:1.5,margin:"0 0 16px"},
  grid:{display:"grid",gap:12,gridTemplateColumns:"repeat(auto-fill,minmax(130px,1fr))"}
};
var Card=({title,description,children})=>h("section",{style:S.card},h("header",{style:S.head},h("h2",{style:S.h2},title),description&&h("p",{style:S.sub},description)),h("div",{style:S.body},children));
var kb=n=>n>1048576?(n/1048576).toFixed(1)+" MB":Math.max(1,Math.round(n/1024))+" KB";

function useLibrary(){
  let[items,setItems]=N.useState(null),[err,setErr]=N.useState(null);
  let load=N.useCallback(()=>{api("media.php",{action:"list"}).then(j=>{setItems(j.items||[]);setErr(null)}).catch(e=>setErr(e.message))},[]);
  N.useEffect(()=>{if(shared())load()},[load]);
  return{items,err,load,setErr}
}
function useUploader(load){
  let[busy,setBusy]=N.useState(0),[msg,setMsg]=N.useState(null),[newIds,setNew]=N.useState([]);
  let run=async files=>{
    files=Array.from(files).filter(f=>/^image\//.test(f.type));
    if(!files.length)return;
    setBusy(b=>b+files.length);setMsg(null);
    let dups=0,okn=0,errs=[],ids=[];
    for(let f of files){try{let j=await up(f);ids.push(j.id);if(j.dup)dups++;else okn++}catch(e){errs.push(e.message)}finally{setBusy(b=>b-1)}}
    setNew(ids);
    let parts=[];if(okn)parts.push(okn+" új kép feltöltve");if(dups)parts.push(dups+" kép már szerepelt a fotótárban, nem töltöttem fel újra");
    setMsg({ok:parts.join(" · "),err:errs.join(" ")});
    load();return ids
  };
  return{busy,msg,run,newIds}
}
function Drop({onFiles,busy}){
  let[over,setOver]=N.useState(false),id=N.useId();
  return h("label",{htmlFor:id,onDragOver:e=>{e.preventDefault();setOver(true)},onDragLeave:()=>setOver(false),onDrop:e=>{e.preventDefault();setOver(false);e.dataTransfer.files.length&&onFiles(e.dataTransfer.files)},style:{display:"block",border:"2px dashed "+(over?"#4f46e5":"#d1d5db"),background:over?"#eef2ff":"#fafafa",borderRadius:12,padding:"22px 16px",textAlign:"center",cursor:"pointer",marginBottom:16}},
    h("div",{style:{fontSize:14,fontWeight:600,color:"#374151"}},busy>0?"Feltöltés… ("+busy+" kép van hátra)":"Képek feltöltése – húzd ide, vagy kattints a tallózáshoz"),
    h("div",{style:{fontSize:12,color:"#6b7280",marginTop:4}},"Több kép is kijelölhető · JPG, PNG, WebP · ugyanaz a kép csak egyszer kerül a tárba"),
    h("input",{id,type:"file",accept:"image/jpeg,image/png,image/webp,image/gif",multiple:true,style:{position:"absolute",width:1,height:1,opacity:0,overflow:"hidden"},onChange:e=>{e.target.files&&e.target.files.length&&onFiles(e.target.files);e.target.value=""}}))
}
function Msg({m}){return m?h(N.Fragment,{},m.ok&&h("p",{style:S.ok,role:"status"},m.ok),m.err&&h("p",{style:S.err,role:"alert"},m.err)):null}

function AiCard(){
  let[st,setSt]=N.useState(null),[key,setKey]=N.useState(""),[busy,setBusy]=N.useState(false),[msg,setMsg]=N.useState(null);
  let load=()=>api("describe.php",{action:"status"}).then(setSt).catch(e=>setMsg({err:e.message}));
  N.useEffect(()=>{load()},[]);
  let act=(body,okt)=>{setBusy(true);setMsg(null);api("describe.php",body).then(()=>{setMsg({ok:okt});setKey("");return load()}).catch(e=>setMsg({err:e.message})).finally(()=>setBusy(false))};
  return h(Card,{title:"Leírás generálása a fotókból (AI)",description:"Új termék feltöltésekor a program a képek alapján magyar és angol leírást javasol, amit utána szabadon átírhatsz. A kép elemzését a Claude (Anthropic) végzi, használat szerint fizetős, leírásonként nagyságrendileg fillérekbe kerül."},
    h(Msg,{m:msg}),
    st&&st.configured?h("div",{style:{display:"flex",flexWrap:"wrap",gap:10,alignItems:"center"}},
      h("span",{style:{fontSize:14,color:"#047857",fontWeight:500}},"Bekapcsolva · API-kulcs: …"+st.last4+" · modell: "+st.model),
      h("button",{style:S.btnR,disabled:busy,onClick:()=>act({action:"clearkey"},"Az API-kulcs törölve, a generálás kikapcsolt.")},"Kulcs törlése"))
    :h("div",{},
      h("p",{style:{fontSize:13,color:"#4b5563",margin:"0 0 10px",lineHeight:1.5}},"Kulcs kérése: console.anthropic.com → API Keys → Create key (és egy kis összeg feltöltése a Billing résznél). A kulcs csak a szerveren tárolódik, a böngészőbe nem kerül vissza."),
      h("div",{style:{display:"flex",gap:8,maxWidth:520,flexWrap:"wrap"}},
        h("input",{type:"password",autoComplete:"off",placeholder:"sk-ant-…",value:key,onChange:e=>setKey(e.target.value),style:{...S.inp,flex:"1 1 260px",width:"auto"}}),
        h("button",{style:{...S.btnD,opacity:busy||!key?.5:1},disabled:busy||!key,onClick:()=>act({action:"setkey",key},"Az API-kulcs elmentve, a generálás be van kapcsolva.")},"Kulcs mentése"))))
}

export function AdminMediaPage(){
  if(!shared())return h("div",{style:S.warn},"A fotótár csak az éles, szerveres működésnél érhető el (api mappa feltöltve, admin belépéssel).");
  let{items,err,load}=useLibrary(),upl=useUploader(load),[sel,setSel]=N.useState(null),[busy,setBusy]=N.useState(false),[msg,setMsg]=N.useState(null);
  let del=it=>{if(!window.confirm("Biztosan törlöd ezt a képet a fotótárból?"))return;setBusy(true);setMsg(null);api("media.php",{action:"delete",id:it.id}).then(()=>{setSel(null);load()}).catch(e=>setMsg({err:e.message})).finally(()=>setBusy(false))};
  let ren=(it,name)=>api("media.php",{action:"rename",id:it.id,name}).then(()=>{load()}).catch(e=>setMsg({err:e.message}));
  return h(N.Fragment,{},
    h(PageHeader,{title:"Fotók",description:"Itt töltöd fel a képeket egyszer, a termékeknél pedig a fotótárból választasz. Ugyanaz a kép nem kerül be kétszer."}),
    h(Card,{title:"Fotótár"+(items?" ("+items.length+" kép)":"")},
      h(Drop,{onFiles:upl.run,busy:upl.busy}),h(Msg,{m:upl.msg}),h(Msg,{m:msg}),err&&h("p",{style:S.err},err),
      !items?h("p",{style:S.sub},"Betöltés…"):items.length===0?h("p",{style:S.sub},"Még nincs kép a fotótárban."):
      h("ul",{style:{...S.grid,listStyle:"none",margin:0,padding:0}},items.map(it=>h("li",{key:it.id,style:{border:"1px solid "+(sel&&sel.id===it.id?"#4f46e5":"#e5e7eb"),borderRadius:10,overflow:"hidden",background:"#fff"}},
        h("button",{type:"button",onClick:()=>setSel(sel&&sel.id===it.id?null:it),style:{display:"block",width:"100%",padding:0,border:0,background:"#f3f4f6",cursor:"pointer"},"aria-label":"Kép kijelölése"},
          h("img",{src:src(it.id),alt:it.name||"",loading:"lazy",style:{display:"block",width:"100%",aspectRatio:"1/1",objectFit:"cover"}})),
        h("div",{style:{padding:"6px 8px",fontSize:11,color:"#6b7280",lineHeight:1.35}},
          h("div",{style:{whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis",color:"#374151"},title:it.name},it.name||"névtelen"),
          h("div",{},it.w+"×"+it.h+" · "+kb(it.size)),
          it.used&&h("div",{style:{color:"#047857",fontWeight:600}},"Használatban"))))),
      sel&&h("div",{style:{marginTop:16,padding:12,border:"1px solid #e5e7eb",borderRadius:10,display:"flex",flexWrap:"wrap",gap:12,alignItems:"center",background:"#fafafa"}},
        h("img",{src:src(sel.id),alt:"",style:{width:72,height:72,objectFit:"cover",borderRadius:8}}),
        h("input",{key:sel.id,defaultValue:sel.name,placeholder:"Kép neve (nem kötelező)",onBlur:e=>{let v=e.target.value.trim();if(v!==sel.name){ren(sel,v)}},style:{...S.inp,flex:"1 1 220px",width:"auto"}}),
        h("button",{style:S.btnR,disabled:busy||sel.used,title:sel.used?"Használatban lévő kép nem törölhető":"",onClick:()=>del(sel)},"Törlés"),
        sel.used&&h("span",{style:{fontSize:12,color:"#6b7280"}},"Termékben vagy tartalomban szerepel, ezért nem törölhető."))),
    h(AiCard,{}))
}

export function MediaPicker({exclude=[],onClose,onPick}){
  let{items,err,load}=useLibrary(),upl=useUploader(load),[sel,setSel]=N.useState([]);
  N.useEffect(()=>{let k=e=>{if(e.key==="Escape")onClose()};window.addEventListener("keydown",k);return()=>window.removeEventListener("keydown",k)},[]);
  let toggle=id=>setSel(s=>s.includes(id)?s.filter(x=>x!==id):[...s,id]);
  let ex=new Set(exclude.map(x=>String(x).replace(/^srv:/,"")));
  let uploadAnd=async f=>{let ids=await upl.run(f);if(ids&&ids.length)setSel(s=>[...s,...ids.filter(i=>!s.includes(i)&&!ex.has(i))])};
  return h("div",{role:"dialog","aria-modal":"true","aria-label":"Választás a fotótárból",style:{position:"fixed",inset:0,zIndex:200,background:"rgba(17,24,39,.55)",display:"flex",alignItems:"center",justifyContent:"center",padding:16},onMouseDown:e=>{if(e.target===e.currentTarget)onClose()}},
    h("div",{style:{background:"#fff",borderRadius:14,width:"100%",maxWidth:860,maxHeight:"90vh",display:"flex",flexDirection:"column",boxShadow:"0 20px 50px rgba(0,0,0,.3)"}},
      h("div",{style:{padding:"14px 18px",borderBottom:"1px solid #e5e7eb",display:"flex",justifyContent:"space-between",alignItems:"center"}},
        h("strong",{style:{fontSize:16}},"Választás a fotótárból"),h("button",{type:"button",style:S.btn,onClick:onClose},"Bezár")),
      h("div",{style:{padding:18,overflowY:"auto",flex:1}},
        h(Drop,{onFiles:uploadAnd,busy:upl.busy}),h(Msg,{m:upl.msg}),err&&h("p",{style:S.err},err),
        !items?h("p",{style:S.sub},"Betöltés…"):items.length===0?h("p",{style:S.sub},"A fotótár még üres, tölts fel képeket fent."):
        h("ul",{style:{...S.grid,gridTemplateColumns:"repeat(auto-fill,minmax(110px,1fr))",listStyle:"none",margin:0,padding:0}},items.map(it=>{let used=ex.has(it.id),on=sel.includes(it.id);
          return h("li",{key:it.id},h("button",{type:"button",disabled:used,onClick:()=>toggle(it.id),"aria-pressed":on,title:used?"Már hozzá van adva ehhez a termékhez":(it.name||""),style:{position:"relative",display:"block",width:"100%",padding:0,border:"3px solid "+(on?"#4f46e5":"transparent"),borderRadius:10,overflow:"hidden",background:"#f3f4f6",cursor:used?"not-allowed":"pointer",opacity:used?.4:1}},
            h("img",{src:src(it.id),alt:it.name||"",loading:"lazy",style:{display:"block",width:"100%",aspectRatio:"1/1",objectFit:"cover"}}),
            on&&h("span",{style:{position:"absolute",top:4,right:4,background:"#4f46e5",color:"#fff",borderRadius:999,fontSize:12,fontWeight:700,width:22,height:22,display:"flex",alignItems:"center",justifyContent:"center"}},sel.indexOf(it.id)+1),
            used&&h("span",{style:{position:"absolute",left:4,bottom:4,background:"rgba(255,255,255,.9)",borderRadius:6,fontSize:10,padding:"1px 5px"}},"hozzáadva")))}))),
      h("div",{style:{padding:"12px 18px",borderTop:"1px solid #e5e7eb",display:"flex",justifyContent:"space-between",alignItems:"center",gap:8}},
        h("span",{style:{fontSize:13,color:"#6b7280"}},sel.length?sel.length+" kép kijelölve (a kijelölés sorrendjében kerülnek be)":"Jelöld ki a képeket"),
        h("button",{type:"button",disabled:!sel.length,style:{...S.btnD,opacity:sel.length?1:.5},onClick:()=>onPick(sel.map(i=>"srv:"+i))},"Hozzáadás"+(sel.length?" ("+sel.length+")":"")))))
}
