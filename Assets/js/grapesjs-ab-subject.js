/**
 * GrapesJS plugin – Alternative Subject Line A/B tester block for Mautic 7+.
 *
 * Registers itself on window.MauticGrapesJsPlugins so Mautic's builder.service
 * can load it automatically (see mautic/mautic PR #12429 pattern).
 */
(function () {
    'use strict';

    if (typeof window.MauticGrapesJsPlugins === 'undefined') {
        window.MauticGrapesJsPlugins = [];
    }

    function AbSubjectPlugin(editor, opts) {
        var options = opts || {};
        var bm = editor.BlockManager;

        bm.add('smartdelay-ab-subject', {
            label: options.label || 'A/B Subject',
            category: options.category || 'Mautic',
            attributes: { class: 'fa fa-random' },
            content: {
                type: 'text',
                content: '<!-- A/B Subject Variant B: [Enter alternative subject here] -->',
                style: { padding: '10px', 'font-style': 'italic', color: '#666' },
                attributes: {
                    'data-smartdelay-ab-subject': 'true',
                    'data-variant': 'B'
                }
            }
        });

        // Optional: expose a simple trait so editors can set the variant label
        editor.DomComponents.addType('smartdelay-ab-subject', {
            isComponent: function (el) {
                return el.getAttribute && el.getAttribute('data-smartdelay-ab-subject') === 'true';
            },
            model: {
                defaults: {
                    traits: [
                        {
                            type: 'text',
                            label: 'Variant label',
                            name: 'data-variant',
                            changeProp: 1
                        }
                    ]
                }
            }
        });
    }

    window.MauticGrapesJsPlugins.push({
        name: 'SmartDelayAbSubject',
        plugin: AbSubjectPlugin,
        context: ['email-html', 'email-mjml'],
        pluginOptions: {
            label: 'A/B Subject Line',
            category: 'Smart Delay'
        }
    });
})();
