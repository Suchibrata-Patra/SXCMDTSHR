"use strict";

function __extends(child, parent) {
    child.prototype = Object.create(parent.prototype);
}

function __mixin(child, parent) {
    for (var p in parent.prototype) {
        // Wie viel wollen wir hinein mischen? Alles!
//        if (parent.prototype.hasOwnProperty(p)) {
            child.prototype[p] = parent.prototype[p];
//        }
    }
}