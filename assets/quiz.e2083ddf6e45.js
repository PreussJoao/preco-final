(function(){
"use strict";
const CHECKOUT="https://pay.cakto.com.br/35dgsa8_1077031";
// The A/B test of the notices ended on 2026-10-24: the sales notice now follows the rules below for everyone.
const GROUP="todos";
// page version (?v=quiz or the one saved in the session); landing straight on the quiz counts as "quiz"
const VARIANT=(()=>{let v=new URLSearchParams(location.search).get("v");try{if(v==="quiz"||v==="direta")sessionStorage.setItem("pc_variant",v);else v=sessionStorage.getItem("pc_variant");}catch(_){}return v==="direta"?"direta":"quiz";})();
const state={stage:null,price:null,costs:[],known:null,partial:null,current:0};
const stepNames=["quiz-intro","quiz-step-1","quiz-step-2","quiz-step-3","quiz-step-4","quiz-result"];
const by=id=>document.getElementById(id);
const money=n=>n.toLocaleString("pt-BR",{style:"currency",currency:"BRL"});
function parseBR(raw){
 let s=String(raw||"").replace(/R\$/gi,"").replace(/\s/g,"");
 if(!s||!/^\d+([.,]\d{1,2})?$/.test(s))return NaN;
 return Number(s.replace(",","."));
}
function withParams(base,keepVersion){try{let u=new URL(base,location.href);new URLSearchParams(location.search).forEach((v,k)=>{if(k!=="popup"&&(keepVersion||k!=="v")&&!u.searchParams.has(k))u.searchParams.set(k,v)});return u.href;}catch(_){return base;}}
function track(event,data={}){try{if(typeof fbq==="function")fbq("trackCustom",event,Object.assign({funnel:"quiz_v3",popup_group:GROUP,variant:VARIANT},data));}catch(_){}}
function go(n){
 state.current=n;
 stepNames.forEach((id,i)=>{const el=by(id);if(el)el.hidden=i!==n;});
 window.scrollTo({top:0,behavior:"instant"});
 const focus=by(n===0?"quiz-intro-title":n===5?"result-title":"step"+n+"-title");
 if(focus){focus.setAttribute("tabindex","-1");focus.focus({preventScroll:true});}
}
function error(n,show){const el=by("error-"+n);if(el)el.hidden=!show;}
document.querySelectorAll("[data-checkout]").forEach(a=>{
 a.href=withParams(CHECKOUT);
 a.addEventListener("click",()=>track("quiz_checkout_clicked",{position:a.dataset.checkout||"result"}));
});
document.querySelectorAll("[data-backlp]").forEach(a=>{
 a.href=withParams(a.getAttribute("href"),true);
});
const start=by("quiz-start");
if(start)start.addEventListener("click",()=>{track("quiz_started");go(1)});
document.querySelectorAll("[data-price]").forEach(b=>b.addEventListener("click",()=>{
 by("unit-price").value=Number(b.dataset.price).toFixed(2).replace(".",",");
 by("unit-price").focus();
 error(2,false);
}));
document.querySelectorAll('[name="stage"],[name="known"]').forEach(i=>i.addEventListener("change",()=>{
 error(i.name==="stage"?1:4,false);
 if(i.name==="known")by("partial-field").hidden=i.value==="no";
}));
document.querySelectorAll('[name="cost"]').forEach(i=>i.addEventListener("change",()=>{
 if(i.value==="unsure"&&i.checked)document.querySelectorAll('[name="cost"]:not([value="unsure"])').forEach(c=>c.checked=false);
 else if(i.value!=="unsure"&&i.checked)document.querySelector('[name="cost"][value="unsure"]').checked=false;
 error(3,false);
}));
document.querySelectorAll("[data-prev]").forEach(b=>b.addEventListener("click",()=>go(Number(b.dataset.prev))));
function validateStep(n){
 if(n===1){
  const sel=document.querySelector('[name="stage"]:checked');
  if(!sel){error(1,true);return false;}
  state.stage=sel.value;return true;
 }
 if(n===2){
  const v=parseBR(by("unit-price").value);
  if(!Number.isFinite(v)||v<1||v>500){error(2,true);return false;}
  state.price=Math.round(v*100)/100;return true;
 }
 if(n===3){
  const selected=Array.from(document.querySelectorAll('[name="cost"]:checked')).map(x=>x.value);
  if(!selected.length){error(3,true);return false;}
  state.costs=selected;return true;
 }
 if(n===4){
  const sel=document.querySelector('[name="known"]:checked');
  if(!sel){error(4,true);return false;}
  state.known=sel.value;state.partial=null;
  if(sel.value!=="no"&&by("partial-cost").value.trim()){
    const v=parseBR(by("partial-cost").value);
    if(!Number.isFinite(v)||v<=0||v>500){error(4,true);return false;}
    state.partial=Math.round(v*100)/100;
  }
  return true;
 }
 return false;
}
document.querySelectorAll("[data-next]").forEach(b=>b.addEventListener("click",()=>{
 const n=Number(b.dataset.next);
 if(!validateStep(n))return;
 track("quiz_question_answered",{step:String(n)});
 if(n===4){renderResult();track("quiz_completed",{profile:resultProfile()});go(5);track("quiz_result_viewed",{profile:resultProfile()});watchResultCta();}
 else go(n+1);
}));
function resultProfile(){
 const essential=["ingredients","packaging","energy","labor","fixed"];
 const missing=essential.filter(x=>!state.costs.includes(x));
 if(state.stage==="starting")return "iniciante";
 if(state.costs.includes("unsure")||missing.length>=3)return "custos_revisar";
 if(missing.length)return "controle_parcial";
 return "controle_mais_completo";
}
const costLabels={ingredients:"ingredientes",packaging:"embalagem",energy:"gás",labor:"seu tempo",fixed:"as contas do mês"};
const lista=xs=>xs.length<2?xs.join(""):xs.slice(0,-1).join(", ")+" e "+xs[xs.length-1];
// Result = short personalised diagnosis + the offer in the first screen. R$ values only when she typed the
// food + packaging cost (then it is an estimate of what is left before the other costs).
function renderResult(){
 const profile=resultProfile(),p=state.price,beginner=state.stage==="starting";
 const titles={
  iniciante:"Você está começando: faça a conta inteira antes de fixar o preço",
  custos_revisar:"Seu preço pode estar abaixo do custo de verdade",
  controle_parcial:"Falta um pedaço da conta no seu preço",
  controle_mais_completo:"Você já olha os custos certos. Falta o número exato"
 };
 by("result-title").textContent=titles[profile];
 const missing=["ingredients","packaging","energy","labor","fixed"].filter(x=>!state.costs.includes(x));
 const cobra=(beginner?"Você pretende cobrar ":"Você cobra ")+money(p)+". ";
 let diag;
 if(state.costs.includes("unsure"))diag="Você não sabe quais custos entram no preço. Sem essa conta, não dá pra saber se sobra dinheiro.";
 else if(missing.length)diag="Não marcou "+lista(missing.map(x=>costLabels[x]))+": se "+(missing.length>1?"não estão":"não está")+" no preço, cada marmita pode sair mais barata do que custa.";
 else diag="Você marcou os principais custos. Falta ver quanto cada um pesa em cada marmita.";
 by("result-diag").textContent=cobra+diag;
 if(state.costs.includes("unsure")){
  by("missing-box").textContent="A calculadora organiza todos os custos pra você: ingredientes, embalagem, gás, contas do mês e o seu tempo. Você só informa os valores.";
 }else if(missing.length){
  by("missing-box").textContent="Confira se estes custos entram no seu preço: "+lista(missing.map(x=>costLabels[x]))+". Se você já conta com eles de outro jeito, ótimo: a calculadora mostra quanto cada um pesa por marmita.";
 }else{
  by("missing-box").textContent="Você marcou os cinco grupos principais de custos. Confira se cada valor está certo e se entram também entrega e taxas, quando existirem.";
 }
 const box=by("remainder-box");
 if(state.partial!==null){
  const remainder=Math.round((p-state.partial)*100)/100;
  box.hidden=false;box.classList.toggle("negative",remainder<0);
  by("result-remainder").textContent=remainder<0?"Comida e embalagem já passam do preço em "+money(-remainder):"Sobram "+money(remainder)+" depois da comida e da embalagem";
  by("remainder-note").textContent="Estimativa: "+money(p)+" − "+money(state.partial)+". "+(remainder<0?"E ainda faltam gás, contas e seu tempo.":"Ainda faltam gás, contas e seu tempo: não é lucro.");
 }else box.hidden=true;
}
// quiz_result_cta_viewed: once per result shown, when the main buy button is actually on screen
let ctaSeen=false,ctaObserver=null;
function watchResultCta(){
 ctaSeen=false;
 const cta=document.querySelector("[data-cta-principal]");
 if(!cta)return;
 const fire=()=>{if(!ctaSeen){ctaSeen=true;track("quiz_result_cta_viewed",{profile:resultProfile()});}};
 if(!("IntersectionObserver" in window)){fire();return;}
 if(ctaObserver)ctaObserver.disconnect();
 ctaObserver=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting&&e.intersectionRatio>=0.6)){fire();ctaObserver.disconnect();}},{threshold:[0,0.6]});
 ctaObserver.observe(cta);
}
const restart=by("restart");
if(restart)restart.addEventListener("click",()=>{
 Object.assign(state,{stage:null,price:null,costs:[],known:null,partial:null,current:0});
 document.querySelectorAll('input[type="checkbox"],input[type="radio"]').forEach(i=>i.checked=false);
 by("unit-price").value="";by("partial-cost").value="";by("partial-field").hidden=true;
 [1,2,3,4].forEach(n=>error(n,false));
 track("quiz_restart");go(0);
});
go(0);
})();