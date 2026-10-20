function r(t){return String(t??"").replace(/[&<>"']/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[t])}function t(t,e="ok"){const a=document.createElement("div");a.className="cm-aviso"+("error"===e?" error":""),a.setAttribute("role","error"===e?"alert":"status"),a.textContent=t,document.body.appendChild(a),setTimeout(()=>a.remove(),"error"===e?6e3:3e3)}function e({titulo:t,ayuda:e="",acciones:a="",cuerpo:i=""}){return`
		<div class="xMiCard xradius inv-card">
			<div class="xEncanezadoCard inv-head">
				<div class="inv-head-top">
					<div><h3>${r(t)}</h3>${e?`<p>${e}</p>`:""}</div>
					<div class="inv-head-acciones">${a}</div>
				</div>
			</div>
			<div class="xContentCard">${i}</div>
		</div>`}var a=(t="")=>`<a class="btn btn-sm btn-light" href="#" data-ir="${r(t)}"><i class="fa fa-arrow-left"></i>Volver</a>`;function i({titulo:i,textoHtml:n,boton:s="Sí, continuar",peligro:o=!0}){return new Promise(t=>{const e=document.createElement("dialog");e.className="cm-dlg cm-app",e.innerHTML=`
			<div class="xdlg-head"><h3><i class="fa ${o?"fa-exclamation-triangle":"fa-question-circle"}" style="color:${o?"#dc3545":"#007bff"};margin-right:6px"></i>${r(i)}</h3></div>
			<div class="xdlg-body" style="font-size:13px">${n}</div>
			<div class="xdlg-foot">
				<button class="btn btn-sm btn-light" data-r="0"><i class="fa fa-times"></i>No, cancelar</button>
				<button class="btn btn-sm ${o?"btn-danger":"btn-success"}" data-r="1"><i class="fa fa-check"></i>${r(s)}</button>
			</div>`,document.body.appendChild(e);let a=!1;e.addEventListener("click",t=>{t=t.target.closest("[data-r]");t&&(a="1"===t.dataset.r,e.close())}),e.addEventListener("close",()=>{e.remove(),t(a)}),e.showModal(),e.querySelector('[data-r="0"]').focus()})}export{r as esc,t as aviso,e as tarjeta,a as volver,i as confirmar};