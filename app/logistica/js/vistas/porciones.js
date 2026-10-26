import{api as r}from"../api.js?v=20261026a";import{esc as c,aviso as d,tarjeta as l,volver as p,autocompletar as e}from"../ui.js?v=20261026a";import{vigente as u,recargar as v}from"../router.js?v=20261026a";import{parecida as b}from"./recibir.js?v=20261026a";const m=a=>Number(a).toLocaleString("es-PE",{maximumFractionDigits:3});async function a(a,e,s){const i=await r("porciones.php?op=lista");if(u(s)){a.innerHTML=l({titulo:"Porciones",ayuda:"Las mismas porciones de tu pantalla <b>Porciones</b>. Indica de qué producto sale cada una (ej. 1 bote = 20 porciones) y envíala a las sedes: cada sede la enlaza con la suya. Así, cuando una sede recibe botes, le suben las porciones solas.",acciones:p("catalogo")+'<button class="btn btn-sm btn-success" id="pc_nueva"><i class="fa fa-plus"></i>Nueva porción</button>',cuerpo:`
			<div class="inv-toolbar">
				<div class="inv-field"><span class="inv-label">Buscar</span><input class="inv-input" id="pc_q" placeholder="Nombre de la porción"></div>
				<span class="inv-sep"></span>
				<p class="inv-ayuda">${i.porciones.length} porciones en la central · ${i.sedes_total} sede(s) participan</p>
			</div>
			<div id="pc_lista"></div>
			<dialog class="lg-dlg" id="pc_dlg"></dialog>`});const n=a.querySelector("#pc_lista"),t=a.querySelector("#pc_q"),o=a.querySelector("#pc_dlg");s=()=>{const e=t.value.trim().toUpperCase();var a=i.porciones.filter(a=>!e||a.descripcion.toUpperCase().includes(e)||a.producto.toUpperCase().includes(e));n.innerHTML=a.length?`<div class="inv-table-wrap"><table class="inv-table">
			<thead><tr><th>Porción</th><th>Sale de</th><th>Rinde</th><th>En sedes</th><th></th></tr></thead>
			<tbody>${a.map(a=>`<tr>
				<td><b>${c(a.descripcion)}</b>${a.codigo?`<span class="inv-sub">Código único ${c(a.codigo)}</span>`:""}</td>
				<td>${a.producto?c(a.producto):'<span class="inv-muted">— no viene del almacén —</span>'}</td>
				<td>${a.producto&&a.factor?`1 = <b>${m(a.factor)}</b> porciones`:'<span class="inv-muted">—</span>'}</td>
				<td><span class="inv-estado ${a.en_sedes<i.sedes_total?"borrador":"aprobado"}">${a.en_sedes}/${i.sedes_total}</span></td>
				<td class="inv-acciones"><span class="inv-link" data-editar="${a.idporcion}"><i class="fa fa-pencil"></i>Editar</span>
					<span class="inv-link" data-enviar="${a.idporcion}"><i class="fa fa-share-alt"></i>Enviar a las sedes</span></td>
			</tr>`).join("")}</tbody></table></div>`:`<p class="inv-empty"><i class="fa fa-pie-chart"></i>${i.porciones.length?"Ninguna porción con ese nombre.":"La central aún no tiene porciones. Crea la primera con <b>Nueva porción</b>."}</p>`};s(),t.addEventListener("input",s),a.querySelector("#pc_nueva").addEventListener("click",()=>f(o,i,null)),n.addEventListener("click",a=>{const e=a.target.closest("[data-editar]"),s=(e&&f(o,i,i.porciones.find(a=>a.idporcion===Number(e.dataset.editar))),a.target.closest("[data-enviar]"));s&&async function(i,n){let a;try{a=await r("porciones.php?op=sedes&idporcion="+n.idporcion)}catch(a){return!d(a.message,"error")}i.innerHTML=`
		<div class="xdlg-head"><h3>Enviar ${c(n.descripcion)} a las sedes</h3>
			<p>Si la sede ya tiene esta porción con otro nombre, elígela: <b>sus recetas siguen igual</b>. Si no la tiene, se le crea.</p></div>
		<div class="xdlg-body"><div class="inv-table-wrap"><table class="inv-table"><thead><tr><th>Sede</th><th>Su porción</th></tr></thead><tbody>
			${a.sedes.map(a=>{if(a.enlazada)return`<tr data-sede="${a.idsede}" data-ya="${a.enlazada.idporcion}"><td>${c(a.sede)}</td><td><i class="fa fa-check-circle" style="color:#28a745"></i> ${c(a.enlazada.descripcion)}</td></tr>`;const e=b(n.descripcion,a.porciones);return`<tr data-sede="${a.idsede}"><td>${c(a.sede)}</td><td><select class="inv-select pe-sel" style="width:100%">
					<option value="0" ${e?"":"selected"}>➕ Crear "${c(n.descripcion)}" en esta sede</option>
					<option value="-1">— No enviar a esta sede —</option>
					${a.porciones.map(a=>`<option value="${a.idporcion}" ${e===a?"selected":""}>Es su ${c(a.descripcion)}</option>`).join("")}
				</select></td></tr>`}).join("")}
		</tbody></table></div></div>
		<div class="xdlg-foot">
			<button class="btn btn-sm btn-light" id="pe_cancelar"><i class="fa fa-times"></i>Cancelar</button>
			<button class="btn btn-sm btn-success" id="pe_ok"><i class="fa fa-share-alt"></i>Enviar</button>
		</div>`,i.querySelector("#pe_cancelar").addEventListener("click",()=>i.close()),i.querySelector("#pe_ok").addEventListener("click",async a=>{var a=a.currentTarget,e=[...i.querySelectorAll("tr[data-sede]")].map(a=>({idsede:Number(a.dataset.sede),idporcion:a.dataset.ya?Number(a.dataset.ya):Number(a.querySelector(".pe-sel").value)})).filter(a=>0<=a.idporcion);a.disabled=!0;try{var s=await r("porciones.php?op=enviar",{idporcion:n.idporcion,sedes:e});i.close(),d(`Listo: ${s.enlazadas} sede(s) enlazadas y ${s.creadas} porción(es) creadas.`),v()}catch(a){d(a.message,"error")}finally{a.disabled=!1}}),i.showModal()}(o,i.porciones.find(a=>a.idporcion===Number(s.dataset.enviar)))})}}function f(s,i,n){s.innerHTML=`
		<div class="xdlg-head"><h3>${n?c(n.descripcion):"Nueva porción"}</h3>
			<p>${n?"El nombre se cambia en la pantalla Porciones.":"Se crea en la central. Después la envías a las sedes."}</p></div>
		<div class="xdlg-body">
			${n?"":`<div class="inv-form-row"><div class="inv-field"><span class="inv-label">Nombre de la porción</span>
				<input class="inv-input inv-w100" id="pf_desc" maxlength="180" placeholder="Ej: HELADO DE FRESA"></div></div>`}
			<div class="inv-form-row"><div class="inv-field"><span class="inv-label">¿De qué producto del almacén sale?</span>
				<input class="inv-input inv-w100" id="pf_prod" maxlength="150" placeholder="Escribe y elige: bote, helado…" value="${c(n&&n.idproducto?n.producto:"")}"></div>
				<span class="inv-sub">Es lo que la central envía a las sedes, ej. BOTE HELADO FRESA. Vacío = no llega del almacén.</span></div>
			<div class="inv-form-row" id="pf_rinde"><span class="inv-ayuda">De 1 <b id="pf_und"></b> salen</span>
				<input class="inv-input" id="pf_porc" type="number" min="1" max="10000" step="any" inputmode="decimal" style="width:90px" value="${n&&n.factor?m(n.factor):""}">
				<span class="inv-ayuda">porciones</span></div>
		</div>
		<div class="xdlg-foot">
			<button class="btn btn-sm btn-light" id="pf_cancelar"><i class="fa fa-times"></i>Cancelar</button>
			<button class="btn btn-sm btn-success" id="pf_guardar"><i class="fa fa-check"></i>Guardar</button>
		</div>`;const t=a=>s.querySelector(a),o=t("#pf_prod");n&&n.idproducto&&(o.dataset.valor=n.idproducto),e(o,i.productos.map(a=>({texto:a.descripcion,valor:String(a.idproducto)})));var a=()=>{t("#pf_rinde").hidden=!o.value.trim(),t("#pf_und").textContent=o.value.trim()};o.addEventListener("input",a),a(),t("#pf_cancelar").addEventListener("click",()=>s.close()),t("#pf_guardar").addEventListener("click",async a=>{a=a.currentTarget;if(t("#pf_porc").validity.badInput)d("Las porciones se escriben solo con números (ej. 20).","error");else{var e=(()=>{const e=o.value.trim().toUpperCase();var a;return e?o.dataset.valor?Number(o.dataset.valor):(a=i.productos.find(a=>a.descripcion.trim().toUpperCase()===e))?Number(a.idproducto):null:0})();if(null===e)d("Ese producto no está en el catálogo: escribe y elígelo de la lista (o déjalo vacío).","error"),o.focus();else{a.disabled=!0;try{await r("porciones.php?op=guardar",{idporcion:n?n.idporcion:0,descripcion:n?void 0:t("#pf_desc").value,idproducto:e,porciones:t("#pf_porc").value}),s.close(),d(n?"Porción guardada. Las sedes ya enlazadas toman el mismo número.":"Porción creada. Ahora envíala a las sedes."),v()}catch(a){d(a.message,"error")}finally{a.disabled=!1}}}}),s.showModal()}export{a as render};