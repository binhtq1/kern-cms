(function(){"use strict";class d{defaults={oldestFirst:!0,text:"Toastify is awesome!",node:void 0,duration:3e3,selector:void 0,callback:function(){},close:!1,gravity:"toastify-top",position:"",className:"",stopOnFocus:!0,onClick:function(){},offset:{x:0,y:0},escapeMarkup:!0,ariaLive:"polite",style:{background:""}};constructor(t){this.options={},this.toastElement=null,this._rootElement=document.body,this._init(t)}showToast(){if(this.toastElement=this._buildToast(),typeof this.options.selector=="string"?this._rootElement=document.getElementById(this.options.selector):this.options.selector instanceof HTMLElement||this.options.selector instanceof ShadowRoot?this._rootElement=this.options.selector:this._rootElement=document.body,!this._rootElement)throw"Root element is not defined";return this._rootElement.insertBefore(this.toastElement,this._rootElement.firstChild),this._reposition(),this.options.duration>0&&(this.toastElement.timeOutValue=window.setTimeout(()=>{this._removeElement(this.toastElement)},this.options.duration)),this}hideToast(){this.toastElement.timeOutValue&&clearTimeout(this.toastElement.timeOutValue),this._removeElement(this.toastElement)}_init(t){this.options=Object.assign(this.defaults,t),this.toastElement=null,this.options.gravity=t.gravity==="bottom"?"toastify-bottom":"toastify-top",this.options.stopOnFocus=t.stopOnFocus===void 0?!0:t.stopOnFocus}_buildToast(){if(!this.options)throw"Toastify is not initialized";let t=document.createElement("div");t.className=`toastify on ${this.options.className} pe-5`,t.className+=` toastify-${this.options.position}`,t.className+=` ${this.options.gravity}`;for(const e in this.options.style)t.style[e]=this.options.style[e];if(this.options.ariaLive&&t.setAttribute("aria-live",this.options.ariaLive),this.options.icon!==""){let e=document.createElement("div");e.className="toastify-icon",e.innerHTML=this.options.icon,t.appendChild(e)}const i=document.createElement("span");if(i.className="toastify-text",this.options.node&&this.options.node.nodeType===Node.ELEMENT_NODE?i.appendChild(this.options.node):this.options.escapeMarkup?i.innerText=this.options.text:i.innerHTML=this.options.text,t.appendChild(i),this.options.close===!0){let e=document.createElement("button");e.type="button",e.setAttribute("aria-label","Close"),e.className="toast-close",e.style.cssText="position: absolute; top: 8px; inset-inline-end: 8px;",e.innerHTML=`<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                <path d="M18 6l-12 12"></path>
                <path d="M6 6l12 12"></path>
            </svg>`,e.addEventListener("click",s=>{s.stopPropagation(),this._removeElement(this.toastElement),window.clearTimeout(this.toastElement.timeOutValue)});const r=window.innerWidth>0?window.innerWidth:screen.width;this.options.position==="left"&&r>360?t.insertAdjacentElement("afterbegin",e):t.appendChild(e)}if(this.options.stopOnFocus&&this.options.duration>0&&(t.addEventListener("mouseover",e=>{window.clearTimeout(t.timeOutValue)}),t.addEventListener("mouseleave",()=>{t.timeOutValue=window.setTimeout(()=>{this._removeElement(t)},this.options.duration)})),typeof this.options.onClick=="function"&&t.addEventListener("click",e=>{e.stopPropagation(),this.options.onClick()}),typeof this.options.offset=="object"){const e=this._getAxisOffsetAValue("x",this.options),r=this._getAxisOffsetAValue("y",this.options),s=this.options.position==="left"?e:`-${e}`,a=this.options.gravity==="toastify-top"?r:`-${r}`;t.style.transform=`translate(${s},${a})`}return t}_removeElement(t){t.className=t.className.replace(" on",""),window.setTimeout(()=>{this.options.node&&this.options.node.parentNode&&this.options.node.parentNode.removeChild(this.options.node),t.parentNode&&t.parentNode.removeChild(t),this.options.callback.call(t),this._reposition()},400)}_reposition(){let t={top:15,bottom:15},i={top:15,bottom:15},e={top:15,bottom:15},r=this._rootElement.querySelectorAll(".toastify"),s;for(let a=0;a<r.length;a++){r[a].classList.contains("toastify-top")===!0?s="toastify-top":s="toastify-bottom";let l=r[a].offsetHeight;s=s.substr(9,s.length-1);let h=15;(window.innerWidth>0?window.innerWidth:screen.width)<=360?(r[a].style[s]=`${e[s]}px`,e[s]+=l+h):r[a].classList.contains("toastify-left")===!0?(r[a].style[s]=`${t[s]}px`,t[s]+=l+h):(r[a].style[s]=`${i[s]}px`,i[s]+=l+h)}}_getAxisOffsetAValue(t,i){return i.offset[t]?isNaN(i.offset[t])?i.offset[t]:`${i.offset[t]}px`:"0px"}}function p(){const o=document.createElement("style");o.textContent=`
        .toastify {
            padding: 0.75rem 2rem 0.75rem 0.75rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow:
                0 3px 6px -1px rgba(0, 0, 0, 0.12),
                0 10px 36px -4px rgba(77, 96, 232, 0.3);
            background: -webkit-linear-gradient(315deg, #73a5ff, #5477f5);
            background: linear-gradient(135deg, #73a5ff, #5477f5);
            position: fixed;
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.215, 0.61, 0.355, 1);
            border-radius: 2px;
            cursor: pointer;
            text-decoration: none;
            z-index: 999999;
            width: 25rem;
            max-width: calc(100% - 30px);
        }

        .toastify.on {
            opacity: 1;
        }

        .toastify-icon {
            width: 1.5rem;
            height: 1.5rem;
        }

        .toast-close {
            background: transparent;
            border: 0;
            color: white;
            cursor: pointer;
            font-family: inherit;
            font-size: 1em;
            opacity: 0.4;
            padding: 0 5px;
            position: absolute;
            top: 0.25rem;
            inset-inline-end: 0.25rem;
        }

        .toast-close svg {
            width: 1em;
            height: 1em;
        }

        .toastify-text a {
            text-decoration: underline;
            color: #fff;
        }

        .toastify-right {
            inset-inline-end: 15px;
        }

        .toastify-left {
            inset-inline-start: 15px;
        }

        .toastify-top {
            top: -150px;
        }

        .toastify-bottom {
            bottom: -150px;
        }

        .toastify-rounded {
            border-radius: 25px;
        }

        .toastify-center {
            margin-inline-start: auto;
            margin-inline-end: auto;
            inset-inline-start: 0;
            inset-inline-end: 0;
            max-width: fit-content;
            max-width: -moz-fit-content;
        }

        @media only screen and (max-width: 360px) {
            .toastify-right,
            .toastify-left {
                margin-inline-start: auto;
                margin-inline-end: auto;
                inset-inline-start: 0;
                inset-inline-end: 0;
                max-width: fit-content;
            }
        }
    `,document.head.appendChild(o)}p();function f(o){return new d(o)}const n=window.Theme||{};window.Theme=n,n.showNotice=function(o,t){let i="#fff",e="";switch(o){case"success":i="#51a351",e='<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>';break;case"danger":i="#bd362f",e='<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 9v4" /><path d="M12 16v.01" /></svg>';break;case"warning":i="#f89406",e='<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 8v4" /><path d="M12 16h.01" /></svg>';break;case"info":i="#2f96b4",e='<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 9h.01" /><path d="M11 12h1v4h1" /></svg>';break}f({text:t,icon:e,duration:5e3,close:!0,gravity:"bottom",position:"right",stopOnFocus:!0,style:{background:i},escapeMarkup:!1}).showToast()},n.showError=function(o){this.showNotice("danger",o)},n.showSuccess=function(o){this.showNotice("success",o)},n.handleError=o=>{typeof o.errors<"u"&&o.errors.length?n.handleValidationError(o.errors):typeof o.responseJSON<"u"?typeof o.responseJSON.errors<"u"?o.status===422&&n.handleValidationError(o.responseJSON.errors):typeof o.responseJSON.message<"u"?n.showError(o.responseJSON.message):n.showError(o.responseJSON.join(", ").join(", ")):n.showError(o.statusText)},n.handleValidationError=o=>{let t="";Object.values(o).forEach(i=>{t!==""&&(t+=`
`),t+=i}),n.showError(t)}})();
