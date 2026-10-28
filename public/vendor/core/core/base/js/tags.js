(function(){"use strict";class r{init(){$(document).find(".tags").each(function(s,i){if($(i).hasClass("tagify"))return;let e=new Tagify(i,{keepInvalidTags:$(i).data("keep-invalid-tags")!==void 0?$(i).data("keep-invalid-tags"):!0,enforceWhitelist:$(i).data("enforce-whitelist")!==void 0?$(i).data("enforce-whitelist"):!1,delimiters:$(i).data("delimiters")!==void 0?$(i).data("delimiters"):",",whitelist:i.value?i.value.trim().split(/\s*,\s*/):[],userInput:$(i).data("user-input")!==void 0?$(i).data("user-input"):!0});$(i).data("url")&&e.on("input",a=>{e.settings.whitelist.length=0,e.loading(!0).dropdown.hide.call(e),$httpClient.make().get($(i).data("url")).then(({data:l})=>{e.settings.whitelist=l,e.loading(!1).dropdown.show.call(e,a.detail.value)})})}),document.querySelectorAll(".list-tagify").forEach(s=>{if(!s.dataset.list||$(s).hasClass("tagify"))return;const i=JSON.parse(s.dataset.list);let e=[];for(const[t,n]of Object.entries(i))e.push({value:t,name:n});let a=String(s.value).split(","),l=e.filter(t=>{if(a.includes(String(t.value)))return{value:t.id,name:t.name}});const d=function(t){return`
                <tag title="${t.title||t.name}"
                        contenteditable='false'
                        spellcheck='false'
                        tabIndex="-1"
                        class="${this.settings.classNames.tag} ${t.class?t.class:""}"
                        ${this.getAttributes(t)}>
                    <x title='' class='tagify__tag__removeBtn' role='button' aria-label='remove tag'></x>
                    <div class="d-flex align-items-center">
                        <span class='tagify__tag-text'>${t.name}</span>
                    </div>
                </tag>
            `},u=function(t){return`
                <div ${this.getAttributes(t)}
                    class="tagify__dropdown__item d-flex align-items-center ${t.class?t.class:""}"
                    tabindex="0"
                    role="option">

                    <div class="d-flex flex-column">
                        <strong>${t.name}</strong>
                    </div>
                </div>
            `};new Tagify(s,{tagTextProp:"name",enforceWhitelist:!0,skipInvalid:!0,dropdown:{closeOnSelect:!1,enabled:0,classname:"users-list",searchKeys:["value","name"]},templates:{tag:d,dropdownItem:u},whitelist:e,originalInputValueFormat:t=>t.map(n=>n.value).join(",")}).loadOriginalValues(l)})}}$(()=>{new r().init()})})();
