function eventBus() {
    const all = new Map();

    return {
        on(type, handler) {
            const handlers = all.get(type)
            if (handlers) {
                handlers.push(handler);
            } else {
                all.set(type, [handler]);
            }
        },

        emit(type, event = {}) {
            let handlers = all.get(type);
            if (handlers) {
                handlers.slice().map((h) => h(event));
            }
        }
    }
}

const EventBus = eventBus();