var style=document.createElement("style");function MultiselectDropdown(e){var s={search:!0,height:"15rem",placeholder:"select",txtSelected:"selected",txtAll:"All",txtRemove:"Remove",txtSearch:"search",...e};function c(e,l){var d=document.createElement(e);return void 0!==l&&Object.keys(l).forEach(t=>{"class"===t?Array.isArray(l[t])?l[t].forEach(e=>""!==e?d.classList.add(e):0):""!==l[t]&&d.classList.add(l[t]):"style"===t?Object.keys(l[t]).forEach(e=>{d.style[e]=l[t][e]}):"text"===t?""===l[t]?d.innerHTML="&nbsp;":d.innerText=l[t]:d[t]=l[t]}),d}document.querySelectorAll("select[multiple]").forEach((d,e)=>{var l=c("div",{class:"multiselect-dropdown",style:{width:s.style?.width??d.clientWidth+"px",padding:s.style?.padding??""}}),o=(d.style.display="none",d.parentNode.insertBefore(l,d.nextSibling),c("div",{class:"multiselect-dropdown-list-wrapper"})),i=c("div",{class:"multiselect-dropdown-list",style:{height:s.height}}),r=c("input",{class:["multiselect-dropdown-search"].concat([s.searchInput?.class??"form-control"]),style:{width:"100%",display:"true"===d.attributes["multiselect-search"]?.value?"block":"none"},placeholder:s.txtSearch});o.appendChild(r),l.appendChild(o),o.appendChild(i),d.loadOptions=()=>{var e,t;i.innerHTML="","true"==d.attributes["multiselect-select-all"]?.value&&(e=c("div",{class:"multiselect-dropdown-all-selector"}),t=c("input",{type:"checkbox"}),e.appendChild(t),e.appendChild(c("label",{text:s.txtAll})),e.addEventListener("click",()=>{e.classList.toggle("checked"),e.querySelector("input").checked=!e.querySelector("input").checked;var t=e.querySelector("input").checked;i.querySelectorAll(":scope > div:not(.multiselect-dropdown-all-selector)").forEach(e=>{"none"!==e.style.display&&(e.querySelector("input").checked=t,e.optEl.selected=t)}),d.dispatchEvent(new Event("change"))}),t.addEventListener("click",e=>{t.checked=!t.checked}),i.appendChild(e)),Array.from(d.options).map(e=>{var t=c("div",{class:e.selected?"checked":"",optEl:e}),l=c("input",{type:"checkbox",checked:e.selected});t.appendChild(l),t.appendChild(c("label",{text:e.text})),t.addEventListener("click",()=>{t.classList.toggle("checked"),t.querySelector("input").checked=!t.querySelector("input").checked,t.optEl.selected=!t.optEl.selected,d.dispatchEvent(new Event("change"))}),l.addEventListener("click",e=>{l.checked=!l.checked}),e.listitemEl=t,i.appendChild(t)}),l.listEl=o,l.refresh=()=>{l.querySelectorAll("span.optext, span.placeholder").forEach(e=>l.removeChild(e));var e=Array.from(d.selectedOptions);e.length>(d.attributes["multiselect-max-items"]?.value??5)?l.appendChild(c("span",{class:["optext","maxselected"],text:e.length+" "+s.txtSelected})):e.map(e=>{var t=c("span",{class:"optext",text:e.text,srcOption:e});"true"!==d.attributes["multiselect-hide-x"]?.value&&t.appendChild(c("span",{class:"optdel",text:"🗙",title:s.txtRemove,onclick:e=>{t.srcOption.listitemEl.dispatchEvent(new Event("click")),l.refresh(),e.stopPropagation()}})),l.appendChild(t)}),0==d.selectedOptions.length&&l.appendChild(c("span",{class:"placeholder",text:d.attributes.placeholder?.value??s.placeholder}))},l.refresh()},d.loadOptions(),r.addEventListener("input",()=>{i.querySelectorAll(":scope div:not(.multiselect-dropdown-all-selector)").forEach(e=>{var t=e.querySelector("label").innerText.toUpperCase();e.style.display=t.includes(r.value.toUpperCase())?"block":"none"})}),l.addEventListener("click",()=>{l.listEl.style.display="block",r.focus(),r.select()}),document.addEventListener("click",function(e){l.contains(e.target)||(o.style.display="none",l.refresh())})})}style.setAttribute("id","multiselect_dropdown_styles"),style.innerHTML=`
.multiselect-dropdown{
  display: inline-block;
  padding: 2px 5px 0px 5px;
  border-radius: 4px;
  border: solid 1px #ced4da;
  background-color: white;
  position: relative;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right .75rem center;
  background-size: 16px 12px;
}
.multiselect-dropdown span.optext, .multiselect-dropdown span.placeholder{
  margin-right:0.5em; 
  margin-bottom:2px;
  padding:1px 0; 
  border-radius: 4px; 
  display:inline-block;
}
.multiselect-dropdown span.optext{
  background-color:lightgray;
  padding:1px 0.75em; 
}
.multiselect-dropdown span.optext .optdel {
  float: right;
  margin: 0 -6px 1px 5px;
  font-size: 0.7em;
  margin-top: 2px;
  cursor: pointer;
  color: #666;
}
.multiselect-dropdown span.optext .optdel:hover { color: #c66;}
.multiselect-dropdown span.placeholder{
  color:#ced4da;
}
.multiselect-dropdown-list-wrapper{
  box-shadow: gray 0 3px 8px;
  z-index: 100;
  padding:2px;
  border-radius: 4px;
  border: solid 1px #ced4da;
  display: none;
  margin: -1px;
  position: absolute;
  top:0;
  left: 0;
  right: 0;
  background: white;
}
.multiselect-dropdown-list-wrapper .multiselect-dropdown-search{
  margin-bottom:5px;
}
.multiselect-dropdown-list{
  padding:2px;
  height: 15rem;
  overflow-y:auto;
  overflow-x: hidden;
}
.multiselect-dropdown-list::-webkit-scrollbar {
  width: 6px;
}
.multiselect-dropdown-list::-webkit-scrollbar-thumb {
  background-color: #bec4ca;
  border-radius:3px;
}

.multiselect-dropdown-list div{
  padding: 5px;
}
.multiselect-dropdown-list input{
  height: 1.15em;
  width: 1.15em;
  margin-right: 0.35em;  
}
.multiselect-dropdown-list div.checked{
}
.multiselect-dropdown-list div:hover{
  background-color: #ced4da;
}
.multiselect-dropdown span.maxselected {width:100%;}
.multiselect-dropdown-all-selector {border-bottom:solid 1px #999;}
`,document.head.appendChild(style),window.addEventListener("load",()=>{MultiselectDropdown(window.MultiselectDropdownOptions)});