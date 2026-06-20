import { Plugin, ButtonView, Widget, toWidget } from 'ckeditor5';

const icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M14 7.94V6a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-1.94l4 2v-8l-4 2z"/></svg>';

function getMimeType(src) {
    const ext = src.split('.').pop().split('?')[0].toLowerCase();
    const types = { mov: 'video/quicktime', mp4: 'video/mp4', webm: 'video/webm', ogv: 'video/ogg', m4v: 'video/mp4' };
    return types[ext] || null;
}

class InsertVideo extends Plugin {
    static get pluginName() {
        return 'InsertVideo';
    }

    static get requires() {
        return [Widget];
    }

    init() {
        const editor = this.editor;

        this._defineSchema();
        this._defineConverters();
        this._defineToolbarButton();
    }

    _defineSchema() {
        this.editor.model.schema.register('videoBlock', {
            isObject: true,
            isBlock: true,
            allowWhere: '$block',
            allowAttributes: ['src', 'controls', 'muted', 'width', 'height', 'type'],
        });
    }

    _defineConverters() {
        const { conversion } = this.editor;

        // Upcast: <figure><video> → videoBlock
        conversion.for('upcast').add(dispatcher => {
            dispatcher.on('element:figure', (evt, data, conversionApi) => {
                const figure = data.viewItem;

                if (!conversionApi.consumable.test(figure, { name: true })) return;

                const video = [...figure.getChildren()].find(c => c.name === 'video');
                if (!video) return;

                const modelEl = conversionApi.writer.createElement('videoBlock', {
                    src: video.getAttribute('src') ?? '',
                    controls: video.hasAttribute('controls'),
                    muted: video.hasAttribute('muted'),
                    width: video.getAttribute('width'),
                    height: video.getAttribute('height'),
                });

                if (!conversionApi.safeInsert(modelEl, data.modelCursor)) return;

                conversionApi.consumable.consume(figure, { name: true });
                conversionApi.consumable.consume(video, { name: true });
                conversionApi.updateConversionResult(modelEl, data);

                evt.stop();
            });
        });

        // Upcast: bare <video> (e.g. from source editing) → videoBlock
        conversion.for('upcast').elementToElement({
            view: 'video',
            model: (viewEl, { writer }) => writer.createElement('videoBlock', {
                src: viewEl.getAttribute('src') ?? '',
                controls: viewEl.hasAttribute('controls'),
                muted: viewEl.hasAttribute('muted'),
                width: viewEl.getAttribute('width'),
                height: viewEl.getAttribute('height'),
            }),
        });

        // Data downcast: videoBlock → <figure><video>
        conversion.for('dataDowncast').elementToElement({
            model: 'videoBlock',
            view: (modelEl, { writer }) => {
                const src = modelEl.getAttribute('src');
                const attrs = { src };
                const type = getMimeType(src);
                if (type) attrs.type = type;
                if (modelEl.getAttribute('controls')) attrs.controls = 'controls';
                if (modelEl.getAttribute('muted')) attrs.muted = 'muted';
                if (modelEl.getAttribute('width')) attrs.width = modelEl.getAttribute('width');
                if (modelEl.getAttribute('height')) attrs.height = modelEl.getAttribute('height');

                return writer.createContainerElement('figure', {},
                    writer.createEmptyElement('video', attrs)
                );
            },
        });

        // Editing downcast: videoBlock → widget
        conversion.for('editingDowncast').elementToElement({
            model: 'videoBlock',
            view: (modelEl, { writer }) => {
                const attrs = {
                    src: modelEl.getAttribute('src'),
                    controls: 'controls',
                    muted: 'muted',
                };
                if (modelEl.getAttribute('width')) attrs.width = modelEl.getAttribute('width');
                if (modelEl.getAttribute('height')) attrs.height = modelEl.getAttribute('height');
                const figure = writer.createContainerElement('figure', {},
                    writer.createEmptyElement('video', attrs)
                );
                return toWidget(figure, writer, { label: 'Video' });
            },
        });
    }

    _defineToolbarButton() {
        const editor = this.editor;

        editor.ui.componentFactory.add('insertVideo', locale => {
            const button = new ButtonView(locale);

            button.set({
                label: 'Insert video',
                icon,
                tooltip: true,
            });

            button.on('execute', () => {
                Craft.createElementSelectorModal('craft\\elements\\Asset', {
                    storageKey: 'CKEditor.InsertVideo',
                    multiSelect: false,
                    criteria: { kind: ['video'] },
                    onSelect: async assets => {
                        if (!assets.length) return;
                        const asset = assets[0];

                        let width = 1920;
                        let height = 1080;

                        try {
                            const response = await Craft.sendActionRequest('GET', 'api/assets/video-orientation', {
                                params: { assetId: asset.id },
                            });
                            if (response.data.orientation === 'portrait') {
                                width = 1080;
                                height = 1920;
                            }
                        } catch (_) {
                            // fall through to landscape defaults
                        }

                        editor.model.change(writer => {
                            const el = writer.createElement('videoBlock', {
                                src: asset.url,
                                controls: true,
                                muted: true,
                                width,
                                height,
                            });
                            editor.model.insertContent(el);
                        });
                    },
                });
            });

            return button;
        });
    }
}

export { InsertVideo };
