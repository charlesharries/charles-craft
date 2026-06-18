(function () {
    const Plugin = CKEditor5.core.Plugin;
    const ButtonView = CKEditor5.ui.ButtonView;

    class InsertVideo extends Plugin {
        static get pluginName() {
            return 'InsertVideo';
        }

        init() {
            const editor = this.editor;

            editor.ui.componentFactory.add('insertVideo', (locale) => {
                const button = new ButtonView(locale);

                button.set({
                    label: 'Insert video',
                    tooltip: true,
                    withText: true,
                });

                button.on('execute', () => {
                    Craft.createElementSelectorModal('craft\\elements\\Asset', {
                        storageKey: 'CKEditor.InsertVideo',
                        multiSelect: false,
                        criteria: { kind: ['video'] },
                        onSelect: (assets) => {
                            if (!assets.length) return;
                            const url = assets[0].url;
                            const html = `<video controls src="${url}"></video>`;
                            const { model, data } = editor;
                            model.change(() => {
                                const viewFrag = data.processor.toView(html);
                                const modelFrag = data.toModel(viewFrag);
                                model.insertContent(modelFrag);
                            });
                        },
                    });
                });

                return button;
            });
        }
    }

    window.CKEditor5.craftVideo = { InsertVideo };
})();
