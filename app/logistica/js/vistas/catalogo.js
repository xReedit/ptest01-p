import{api as c}from"../api.js?v=20261015i";import{esc as l,aviso as d,tarjeta as a,volver as t,confirmar as p,paginador as u}from"../ui.js?v=20261015i";import{abrirForm as n,leerPresentaciones as m}from"./producto_form.js?v=20261015i";let v=null;async function e(s,i){s.innerHTML=a({titulo:"Catálogo de la central",ayuda:"Los productos se crean aquí y se envían a todas las sedes. Cada sede pone su propio precio y stock.",acciones:t()+`
			<a class="btn btn-sm btn-light" id="ct_importar" href="#" data-ir="importar" hidden><i class="fa fa-file-excel-o"></i>Importar Excel</a>
			<a class="btn btn-sm btn-light" id="ct_porciones" href="#" data-ir="porciones" hidden><i class="fa fa-pie-chart"></i>Porciones</a>
			<button class="btn btn-sm btn-light" id="ct_replicar" hidden><i class="fa fa-share-alt"></i>Enviar a las sedes</button>
			<button class="btn btn-sm btn-success" id="ct_nuevo" hidden><i class="fa fa-plus"></i>Nuevo producto</button>`,cuerpo:`
			<div class="inv-toolbar">
				<div class="inv-field"><span class="inv-label">Buscar</span><input class="inv-input" id="ct_q" placeholder="Producto o familia"></div>
				<span class="inv-sep"></span>
				<p class="inv-ayuda" id="ct_resumen"></p>
			</div>
			<div id="ct_lista"><p class="inv-empty"><i class="fa fa-spinner fa-spin"></i>Cargando…</p></div>
			<dialog class="lg-dlg" id="ct_dlg"></dialog>`});const o=s.querySelector("#ct_q");let e=null;g=1,o.addEventListener("input",()=>{clearTimeout(e),e=setTimeout(()=>{g=1,f(s,i,o.value)},300)}),s.querySelector("#ct_nuevo").addEventListener("click",()=>n(s.querySelector("#ct_dlg"),v,null,r(s,i))),s.querySelector("#ct_replicar").addEventListener("click",e=>async function(i,o,n){n.disabled=!0;try{var r=await c("catalogo.php?op=pendientes_envio");if(r.pendientes){if(await p({titulo:"¿Enviar el catálogo a las sedes?",peligro:!1,boton:`Sí, enviar ${r.pendientes} productos`,textoHtml:`
				<p style="margin:0 0 8px">Hay <b>${r.pendientes}</b> productos del catálogo que todavía <b>no existen</b> en una o más de tus
					<b>${r.sedes}</b> sedes. Esto los crea en esas sedes para que puedan pedirlos, recibirlos y venderlos.</p>
				<ul style="margin:0 0 8px;padding-left:18px">
					<li>Llegan con <b>stock 0</b> y con el <b>precio de venta del catálogo</b>. Después cada sede puede cambiar su precio.</li>
					<li>Si una sede ya tiene un producto con el mismo nombre, <b>no se duplica</b>: lo verás en
						<b>Productos de las sedes</b> para vincularlo.</li>
					<li>No borra ni cambia nada de lo que las sedes ya tienen.</li>
				</ul>`})){let e=0,a=0,t=0,s=r.pendientes;for(;0<s;){n.innerHTML='<i class="fa fa-spinner fa-spin"></i>Enviando… faltan '+s;var l=await c("catalogo.php?op=enviar_pendientes",{despues_de:e});if(l.ultimo===e)break;e=l.ultimo,a+=l.creado,t+=l.conflicto.length,s=l.quedan}d(`Listo: ${a} productos creados en las sedes.`+(t?` ${t} ya existían con ese nombre: vincúlalos en Productos de las sedes.`:""),t?"error":"ok"),await f(i,o,i.querySelector("#ct_q").value)}}else d("Todas las sedes ya tienen todo el catálogo.")}catch(e){d(e.message,"error")}finally{n.disabled=!1}}(s,i,e.currentTarget)),s.querySelector("#ct_lista").addEventListener("click",e=>{const a=e.target.closest("[data-editar]");a&&n(s.querySelector("#ct_dlg"),v,v.productos.find(e=>String(e.idproducto)===a.dataset.editar),r(s,i));var t=e.target.closest("[data-pag]"),t=(t&&(g=Number(t.dataset.pag),f(s,i,o.value).then(()=>s.scrollIntoView({block:"start"}))),e.target.closest("[data-eliminar]"));t&&!async function(e,a,t){try{var s,i=await c("catalogo.php?op=antes_eliminar&idproducto="+t),o=Number(i.stock);await p({titulo:"¿Eliminar este producto?",boton:"Sí, eliminar",textoHtml:`
				<p style="margin:0 0 8px"><b>${l(i.descripcion)}</b></p>
				<ul style="margin:0 0 8px;padding-left:18px">
					<li>Se quitará del catálogo de la central${i.sedes?` y de <b>${i.sedes}</b> sede(s)`:""}.</li>
					<li>Ya no se podrá comprar, pedir ni trasladar.</li>
					<li>Las ventas, compras y movimientos ya registrados <b>no se pierden</b>.</li>
				</ul>
				`+(0<o?`<p class="inv-neg" style="margin:0"><i class="fa fa-exclamation-circle"></i> Todavía hay <b>${l(String(o))}</b> en stock entre todas las sedes.</p>`:"")})&&(s=await c("catalogo.php?op=eliminar",{idproducto:t}),d(`Producto eliminado (${s.eliminados} registro(s)).`),await f(e,a,e.querySelector("#ct_q").value))}catch(e){d(e.message,"error")}}(s,i,Number(t.dataset.eliminar))}),await f(s,i,"")}let b=0,g=1;async function f(e,a,t){var s=e.querySelector("#ct_lista"),i=++b;try{var o=await c("catalogo.php?op=listar&q="+encodeURIComponent(t)+"&pagina="+g);if(i!==b||!s.isConnected)return;v=o}catch(e){return i===b&&s.isConnected?void(s.innerHTML=`<div class="lg-error">${l(e.message)}</div>
			<p style="margin-top:12px"><a class="btn btn-sm btn-success" href="#" data-ir="config"><i class="fa fa-cog"></i>Configurar Logística</a></p>`):void 0}const n=Number(v.org.idsede_central)===Number(a.idsede),r=Number(v.sedes_total);e.querySelector("#ct_nuevo").hidden=!n,e.querySelector("#ct_importar").hidden=!n,e.querySelector("#ct_porciones").hidden=!n;t=e.querySelector("#ct_replicar");t.hidden=!n||0===r,t.innerHTML='<i class="fa fa-share-alt"></i>Enviar a las sedes',t.title="Crea en las sedes los productos del catálogo que todavía no tienen",g=v.meta.pagina,e.querySelector("#ct_resumen").textContent=`${v.meta.total} productos · ${r} sede(s) participan`+(n?"":" · solo lectura: se edita desde la central"),s.innerHTML=v.productos.length?`
		<div class="inv-table-wrap"><table class="inv-table">
			<thead><tr><th>Familia</th><th>Producto</th><th class="lg-oc">Unidad</th><th style="text-align:right">Precio</th><th class="lg-oc">Se compra por</th><th class="lg-oc">Proveedor</th><th>En sedes</th><th></th></tr></thead>
			<tbody>${v.productos.map(e=>`
				<tr>
					<td>${l(e.familia)}</td>
					<td>${l(e.descripcion)}${e.porciones?`<span class="inv-sub" title="Al recibir, la sede suma porciones. Cada sede enlaza su porción la primera vez que le llega.">
						<i class="fa fa-pie-chart"></i> 1 ${l(e.unidad||"und")} = ${Number(e.porciones)} porciones · enlazada en ${e.n_porcion}/${r} sedes</span>`:""}</td>
					<td class="lg-oc">${l(e.unidad)||'<span class="inv-muted">UND</span>'}</td>
					<td style="text-align:right">${Number(e.precio_venta||0)?"S/ "+Number(e.precio_venta).toFixed(2):'<span class="inv-muted">—</span>'}</td>
					<td class="lg-oc">${m(e.presentaciones).map(e=>l(e.nombre+" x"+e.factor)).join(", ")||'<span class="inv-muted">—</span>'}</td>
					<td class="lg-oc">${l(e.proveedor)||'<span class="inv-muted">—</span>'}</td>
					<td><span class="inv-estado ${Number(e.n_sedes)<r?"borrador":"aprobado"}">${e.n_sedes}/${r}</span></td>
					<td class="inv-acciones">${n?`<span class="inv-link" data-editar="${e.idproducto}"><i class="fa fa-pencil"></i>Editar</span><span class="inv-link inv-link-rojo" data-eliminar="${e.idproducto}"><i class="fa fa-trash-o"></i>Eliminar</span>`:""}</td>
				</tr>`).join("")}
			</tbody>
		</table></div>
		`+u(v.meta):'<p class="inv-empty"><i class="fa fa-book"></i>Aún no hay productos. Crea uno o importa tu Excel.</p>'}function r(s,i){return async(e,a)=>{var t;d(a?(a=e.replicado,t=`Creado en ${a.creado} sede(s).`,a.conflicto.length?t+` ${a.conflicto.length} no se crearon porque la sede ya tiene un producto con ese nombre (vincúlalo a mano).`:t):`Guardado en ${e.sedes_actualizadas} sedes.`),await f(s,i,s.querySelector("#ct_q").value)}}export{e as render};