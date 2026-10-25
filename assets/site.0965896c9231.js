(function(){
"use strict";
const CHECKOUT="https://pay.cakto.com.br/35dgsa8_1077031";
// The A/B test of the notices ended on 2026-10-24: the sales notice now follows the rules below for everyone.
const GROUP="todos";
// page version: plain URL = "direta"; ?v=quiz = "quiz" (set on <html> by the inline script in <head>)
const VARIANT=document.documentElement.classList.contains("v-quiz")?"quiz":"direta";
function withParams(base,keepVersion){
 try{const url=new URL(base,location.href);const incoming=new URLSearchParams(location.search);
 incoming.forEach((v,k)=>{if(k!=="popup"&&(keepVersion||k!=="v")&&!url.searchParams.has(k))url.searchParams.set(k,v);});
 return url.href;}catch(_){return base;}
}
function track(name,data){
 try{if(typeof window.fbq==="function")window.fbq("trackCustom",name,Object.assign({funnel:"lp_v3",popup_group:GROUP,variant:VARIANT},data||{}));}catch(_){}
}
document.querySelectorAll("[data-checkout]").forEach(a=>{
 a.href=withParams(CHECKOUT);
 a.addEventListener("click",()=>track("lp_checkout_clicked",{position:a.dataset.checkout||"unknown"}));
});
document.querySelectorAll("[data-quizlink]").forEach(a=>{
 a.href=withParams(a.getAttribute("href"),true); // keeps ?v=quiz so the quiz knows the version
 a.addEventListener("click",()=>track("lp_quiz_clicked",{position:"lp"}));
});

// Video at the top: a light silent copy (data-src, ~0,7 MB) plays muted in a loop while at least 30% visible (only after the page has painted, so it
// never delays the first screen); a tap restarts it with sound and controls, in full screen when the browser allows.
// Events carry modo: "auto" (muted autoplay) or "toque" (the person chose to watch). Coverage counts unique played
// ranges, so skipping or repeating adds nothing; each milestone is sent once per mode and page load.
// The full video with sound (data-full) only downloads when the person taps to watch.
const video=document.querySelector("video.demo-video");
if(video){
 const milestones=[25,50,75,95],tapButton=document.querySelector("[data-video-toque]");
 let modo=null,sent=new Set(),started=false,completed=false,visible=false,ready=false;
 const reset=m=>{modo=m;sent=new Set();started=false;completed=false;};
 const measure=()=>{
  const duration=video.duration;
  if(!started||!Number.isFinite(duration)||duration<=0)return null;
  const ranges=video.played;
  let watched=0;
  for(let i=0;i<ranges.length;i++){
   watched+=Math.max(0,Math.min(duration,ranges.end(i))-Math.max(0,ranges.start(i)));
  }
  watched=Math.min(duration,watched);
  return {video_id:"demonstracao",modo,percent:watched/duration*100,
   duration_seconds:Math.round(duration*100)/100,watched_seconds:Math.round(watched*100)/100};
 };
 const updateProgress=()=>{
  const data=measure();if(!data)return null;
  milestones.forEach(percent=>{
   if(data.percent>=percent&&!sent.has(percent)){
    sent.add(percent);track("lp_video_progress",Object.assign({},data,{percent}));
   }
  });
  return data;
 };
 const source=()=>{if(!video.getAttribute("src"))video.src=video.dataset.src;};
 const saveData=!!(navigator.connection&&navigator.connection.saveData);
 const playMuted=()=>{
  if(modo==="toque"||saveData)return;
  if(modo!=="auto")reset("auto");
  source();video.muted=true;
  const p=video.play();if(p&&p.catch)p.catch(()=>{});
 };
 const watch=()=>{
  reset("toque");
  video.loop=false;video.muted=false;video.controls=true;
  if(tapButton)tapButton.hidden=true;
  if(video.dataset.full)video.src=video.dataset.full;else source();
  video.load(); // full video, from the start, with fresh played ranges for the chosen viewing
  const p=video.play();if(p&&p.catch)p.catch(()=>{});
  const full=video.requestFullscreen||video.webkitRequestFullscreen;
  try{
   if(full){const r=full.call(video);if(r&&r.catch)r.catch(()=>{});}
   else if(video.webkitEnterFullscreen)video.webkitEnterFullscreen();
  }catch(_){}
 };
 if(tapButton)tapButton.addEventListener("click",watch);
 video.addEventListener("click",()=>{if(modo!=="toque")watch();});
 video.addEventListener("playing",()=>{
  if(!started&&modo){started=true;track("lp_video_started",{video_id:"demonstracao",modo});}
 });
 video.addEventListener("timeupdate",updateProgress);
 video.addEventListener("pause",updateProgress);
 video.addEventListener("ended",()=>{
  const data=updateProgress();
  // Allow 1% for native timing precision; reaching the end alone is insufficient.
  if(data&&data.percent>=99&&!completed){
   completed=true;track("lp_video_completed",Object.assign({},data,{percent:Math.round(data.percent*100)/100}));
  }
 });
 const release=()=>{ready=true;if(visible)playMuted();};
 if(document.readyState==="complete")setTimeout(release,1200);else window.addEventListener("load",()=>setTimeout(release,1200));
 if("IntersectionObserver" in window){
  new IntersectionObserver(entries=>{
   visible=entries.some(e=>e.isIntersecting&&e.intersectionRatio>=0.3);
   if(visible){if(ready)playMuted();}else if(modo==="auto")video.pause();
  },{threshold:[0,0.3]}).observe(video);
 }else visible=true;
}

const STEPS=[
 {img:"demo-1-despesas.b051ce2e0d6a.webp",alt:"Tela real com despesas fixas e quantidade de marmitas",title:"Primeiro, os gastos do seu negócio",desc:"Informe despesas fixas, quantos dias trabalha e quantas marmitas faz. A ferramenta calcula quanto desses custos corresponde a cada unidade."},
 {img:"demo-2-busca.0be5bf3d15ca.webp",alt:"Busca de ingredientes na lista da ferramenta",title:"Busque os ingredientes que você usa",desc:"Pesquise arroz, feijão, frango e outros ingredientes disponíveis na base. Se não encontrar, pode incluir um ingrediente personalizado."},
 {img:"demo-3-quantidade.86855942c0c7.webp",alt:"Tela real para informar o preço pago, peso e quantidade usada",title:"Diga quanto pagou e quanto usou",desc:"Informe o preço da compra, a unidade de medida e a quantidade de cada ingrediente que entrou na receita, além do custo da embalagem."},
 {img:"demo-4-calculo.639638a35764.webp",alt:"Tela com lista de ingredientes e botão Ver meu preço",title:"Revise a receita antes de calcular",desc:"A lista mostra os ingredientes adicionados, seus valores e a embalagem. Ao concluir, você avança para o cálculo do preço."},
 {img:"demo-5-preco.ef5d9735ec48.webp",alt:"Tela real de resultado com preço recomendado e custos por marmita",title:"Veja seu custo e o preço recomendado",desc:"Consulte a composição do custo, o limite mínimo e o preço recomendado, considerando os dados e a margem que você informou."}
];
const tabs=Array.from(document.querySelectorAll("[data-tab]"));
if(tabs.length){
 const img=document.getElementById("demo-img"),txt=document.getElementById("step-title"),desc=document.getElementById("step-desc"),index=document.getElementById("step-index"),panel=document.getElementById("demo-painel");
 function pick(i,focus){
  const item=STEPS[i];if(!item)return;
  tabs.forEach((t,j)=>{t.setAttribute("aria-selected",j===i?"true":"false");t.tabIndex=j===i?0:-1;});
  img.src="assets/"+item.img;img.alt=item.alt;txt.textContent=item.title;desc.textContent=item.desc;index.textContent="Etapa "+(i+1)+" de 5";panel.setAttribute("aria-labelledby","tab-"+i);
  if(focus)tabs[i].focus();
  track("lp_demo_step",{step:String(i+1)});
 }
 tabs.forEach((b,i)=>{
   b.addEventListener("click",()=>pick(i,false));
   b.addEventListener("keydown",e=>{
     if(!["ArrowLeft","ArrowRight","Home","End"].includes(e.key))return;
     e.preventDefault();const k=e.key==="Home"?0:e.key==="End"?tabs.length-1:(i+(e.key==="ArrowRight"?1:-1)+tabs.length)%tabs.length;pick(k,true);
   });
 });
}
const sticky=document.getElementById("mobile-sticky"),hero=document.querySelector(".hero"),offer=document.getElementById("comprar");
if(sticky&&hero&&"IntersectionObserver"in window){
 let heroVisible=true,offerVisible=false;
 const update=()=>sticky.classList.toggle("visible",!heroVisible&&!offerVisible);
 new IntersectionObserver(entries=>{heroVisible=entries[0].isIntersecting;update();},{threshold:0}).observe(hero);
 if(offer)new IntersectionObserver(entries=>{offerVisible=entries[0].isIntersecting;update();},{threshold:0}).observe(offer);
}

// Sales notice (real number of approved purchases from vendas.json), once per session and never right at load:
// after 45 s or 70% of the page scrolled (on phones at the top of the screen, so it never covers a buy button or the
// bottom bar). Leaving through the top of the window now opens the downsell instead. Never shown to someone who
// already clicked to buy, opened the quiz or saw the downsell in this session.
const NOTICE_AFTER=45000,NOTICE_SCROLL=0.7,NOTICE_VISIBLE=12000;
const session=(k,v)=>{try{if(v===undefined)return sessionStorage.getItem(k);sessionStorage.setItem(k,v);}catch(_){}return null;};
document.querySelectorAll("[data-checkout],[data-quizlink]").forEach(a=>a.addEventListener("click",()=>session("pc_clicou","1")));
if(document.body.classList.contains("page-lp")&&!session("pc_aviso")&&!session("pc_clicou")){
 fetch("vendas.json",{cache:"no-store"}).then(r=>r.ok?r.json():null).catch(()=>null).then(data=>{
  const n=data&&Number.isInteger(data.compras)&&data.compras>0&&data.compras<1e6?data.compras:0;
  if(!n)return;
  const box=document.createElement("div");
  box.className="notice";box.setAttribute("role","status");box.setAttribute("aria-live","polite");
  const icon=document.createElement("span");icon.className="notice-icon";icon.setAttribute("aria-hidden","true");icon.textContent=String(n);
  const text=document.createElement("p"),b=document.createElement("b"),small=document.createElement("small");
  b.textContent=n===1?"1 pessoa":n+" pessoas";small.textContent="Compras confirmadas do Preço Certo";
  text.append(b,n===1?" já começou a calcular o preço certo da marmita.":" já começaram a calcular o preço certo da marmita.",small);
  const cta=document.createElement("a");cta.className="notice-cta";cta.href=withParams(CHECKOUT);cta.textContent="Quero o meu · R$ 29,99 →";
  cta.addEventListener("click",()=>{session("pc_clicou","1");session("pc_checkout29",String(Date.now()));track("lp_checkout_clicked",{position:"popup"});});
  text.append(cta);
  const close=document.createElement("button");close.type="button";close.className="notice-close";close.setAttribute("aria-label","Fechar aviso");close.textContent="×";
  box.append(icon,text,close);document.body.appendChild(box);
  let shown=false,tries=0;
  const phone=matchMedia("(hover: none), (max-width: 850px)").matches;
  // on phones the notice sits at the top: wait while a buy button is under that strip
  const coversButton=()=>Array.from(document.querySelectorAll("main [data-checkout]")).some(a=>{const r=a.getBoundingClientRect();return r.height>0&&r.top<190&&r.bottom>0;});
  const show=trigger=>{
   if(shown||session("pc_aviso")||session("pc_clicou")||session("pc_downsell"))return;
   if(document.fullscreenElement||document.webkitFullscreenElement){setTimeout(()=>show(trigger),2000);return;} // watching the video in full screen
   if(phone&&coversButton()&&tries++<20){setTimeout(()=>show(trigger),1500);return;}
   shown=true;session("pc_aviso","1");box.classList.add("on");
   track("lp_popup_shown",{kind:"vendas",aviso:"vendas_1",count:n,gatilho:trigger});
   setTimeout(()=>box.classList.remove("on"),NOTICE_VISIBLE);
  };
  close.addEventListener("click",()=>{box.classList.remove("on");track("lp_popup_closed",{aviso:"vendas_1"});});
  {
   setTimeout(()=>show("tempo"),NOTICE_AFTER);
   const onScroll=()=>{
    const de=document.documentElement;
    if((window.scrollY+window.innerHeight)/de.scrollHeight>=NOTICE_SCROLL){window.removeEventListener("scroll",onScroll);show("rolagem");}
   };
   window.addEventListener("scroll",onScroll,{passive:true});
  }
 });
}

// Downsell: the same Preço Certo for R$ 19,99 (Cakto link below), offered once per session to someone leaving:
// computer: the mouse leaves through the top; anywhere: coming back from the R$ 29,99 checkout without buying,
// or coming back to the tab after 10 s away ; phone: a fast scroll back up after reading half the page.
// The back button is never hijacked (Meta rejects landing pages that do it).
const DOWNSELL="https://pay.cakto.com.br/3ckby5v";
document.querySelectorAll("[data-checkout]").forEach(a=>a.addEventListener("click",()=>session("pc_checkout29",String(Date.now()))));
const downsell=(()=>{
 let box=null;
 const close=how=>{
  if(!box||box.hidden)return;
  box.classList.remove("on");document.body.classList.remove("ds-aberto");
  setTimeout(()=>{box.hidden=true;},250);
  track("downsell_closed",{como:how});
 };
 const build=()=>{
  box=document.createElement("div");box.className="ds";box.hidden=true;
  box.setAttribute("role","dialog");box.setAttribute("aria-modal","true");box.setAttribute("aria-labelledby","ds-titulo");
  box.innerHTML='<div class="ds-caixa"><button type="button" class="ds-x" aria-label="Fechar">×</button>'+
   '<p class="ds-tag">Antes de você sair</p><h2 id="ds-titulo" tabindex="-1">Leve o Preço Certo por R$ 19,99</h2>'+
   '<p class="ds-preco"><s>R$ 29,99</s> <b>R$ 19,99</b></p>'+
   '<p class="ds-texto">O mesmo Preço Certo: calculadora no celular, custo e preço de cada marmita e tutorial. Pagamento único.</p>'+
   '<a class="btn btn-block" data-downsell>Quero por R$ 19,99 →</a>'+
   '<p class="fineprint ds-micro">Pix ou cartão · acesso logo após pagar · já com a taxa da plataforma</p>'+
   '<button type="button" class="ds-nao">Agora não</button></div>';
  document.body.appendChild(box);
  const cta=box.querySelector("[data-downsell]");cta.href=withParams(DOWNSELL);
  cta.addEventListener("click",()=>{session("pc_clicou","1");track("lp_checkout_clicked",{position:"downsell",oferta:"19,99"});});
  box.querySelector(".ds-x").addEventListener("click",()=>close("x"));
  box.querySelector(".ds-nao").addEventListener("click",()=>close("agora_nao"));
  box.addEventListener("click",e=>{if(e.target===box)close("fora");});
  document.addEventListener("keydown",e=>{if(e.key==="Escape")close("esc");});
 };
 const open=trigger=>{
  if(session("pc_downsell"))return;
  if(document.fullscreenElement||document.webkitFullscreenElement){setTimeout(()=>open(trigger),2000);return;}
  session("pc_downsell","1");session("pc_aviso","1");
  document.querySelectorAll(".notice.on").forEach(n=>n.classList.remove("on"));
  build();box.hidden=false;document.body.classList.add("ds-aberto");
  requestAnimationFrame(()=>box.classList.add("on"));
  box.querySelector("h2").focus({preventScroll:true});
  track("downsell_shown",{gatilho:trigger,oferta:"19,99"});
 };
 return {open};
})();
const backFromCheckout=()=>!!session("pc_checkout29");
window.addEventListener("pageshow",()=>{if(backFromCheckout())setTimeout(()=>downsell.open("volta_checkout"),700);});
if(matchMedia("(hover: hover) and (pointer: fine)").matches){
 const since=Date.now();
 document.addEventListener("mouseout",e=>{if(!e.relatedTarget&&e.clientY<=0&&Date.now()-since>5000)downsell.open("saida");});
}else{
 // phone: a fast swipe back up (1000 px in under half a second) after reading half the page reads as "leaving"
 const since=Date.now();let deepest=0,lastY=window.scrollY,lastT=performance.now();
 window.addEventListener("scroll",()=>{
  const de=document.documentElement,y=window.scrollY,t=performance.now();
  deepest=Math.max(deepest,(y+window.innerHeight)/de.scrollHeight);
  if(deepest>=0.5&&Date.now()-since>15000&&lastY-y>=1000&&t-lastT<500)downsell.open("subida_rapida");
  if(t-lastT>=500||y>lastY){lastY=y;lastT=t;}
 },{passive:true});
}
let hiddenAt=0;
document.addEventListener("visibilitychange",()=>{
 if(document.hidden){hiddenAt=Date.now();return;}
 if(hiddenAt&&Date.now()-hiddenAt>=10000)downsell.open(backFromCheckout()?"volta_checkout":"volta_aba");
});
})();
