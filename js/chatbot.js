(function(){
var open=document.getElementById('cb-open'),box=document.getElementById('cb');if(!open)return;
var log=document.getElementById('cb-log'),chips=document.getElementById('cb-chips'),form=document.getElementById('cb-form'),inp=document.getElementById('cb-input');
var D=document.body.dataset;
function add(t,me){var d=document.createElement('div');d.className='cb-msg'+(me?' me':'');d.textContent=t;log.appendChild(d);log.scrollTop=log.scrollHeight;return d}
function setChips(a){chips.innerHTML='';(a||[]).forEach(function(c){var b=document.createElement('button');b.type='button';b.textContent=c;b.onclick=function(){send(c)};chips.appendChild(b)})}
function send(t){t=t.trim();if(!t)return;add(t,true);inp.value='';var w=add('…');
  fetch(D.base+'chatbot.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':D.csrf},body:JSON.stringify({q:t})})
  .then(function(r){if(r.status===401){location.href=D.base+'login.php?timeout=1';throw 0}if(!r.ok)throw 1;return r.json()})
  .then(function(d){w.textContent=d.reply;setChips(d.chips);log.scrollTop=log.scrollHeight})
  .catch(function(e){if(e!==0)w.textContent='Қате шықты. Қайталап көріңіз.'})}
chips.addEventListener('click',function(e){if(e.target.tagName==='BUTTON'&&!e.target.onclick)send(e.target.textContent)});
form.addEventListener('submit',function(e){e.preventDefault();send(inp.value)});
open.onclick=function(){box.hidden=!box.hidden;if(!box.hidden)inp.focus()};
document.getElementById('cb-close').onclick=function(){box.hidden=true};
})();
