@once
    <script>
        window.lazyLoadShortcodeBlocks = function () {
            document.querySelectorAll('.shortcode-lazy-loading:not([data-loading])').forEach(async function (element) {
                element.dataset.loading = 'true';
                element.setAttribute('aria-busy', 'true');
                var name = element.dataset.name;
                var attributes;
                try {
                    attributes = JSON.parse(element.dataset.attributes || '{}');
                    var response = await fetch({{ Js::from(route('public.ajax.render-ui-block')) }}, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': {{ Js::from(csrf_token()) }}
                        },
                        body: JSON.stringify({ name: name, attributes: attributes })
                    });
                    var result = await response.json();
                    if (!response.ok || result.error) throw new Error(result.message || 'Unable to load content.');
                    var template = document.createElement('template');
                    template.innerHTML = result.data;
                    // Inline scripts returned by custom shortcodes must be registered through the asset pipeline.
                    element.replaceWith(template.content);
                    document.dispatchEvent(new CustomEvent('shortcode.loaded', {
                        detail: { name: name, attributes: attributes, html: result.data }
                    }));
                } catch (error) {
                    delete element.dataset.loading;
                    element.removeAttribute('aria-busy');
                    element.dispatchEvent(new CustomEvent('shortcode.error', { bubbles: true, detail: { error: error } }));
                }
            });
        };
        if (document.readyState === 'complete') window.lazyLoadShortcodeBlocks();
        else window.addEventListener('load', window.lazyLoadShortcodeBlocks, { once: true });
    </script>
@endonce
