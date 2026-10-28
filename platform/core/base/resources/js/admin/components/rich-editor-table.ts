import { TableView } from "@tiptap/extension-table";
import type { Node as ProseMirrorNode } from "@tiptap/pm/model";

/** TableView recalculates column widths on every update; restore the saved table presentation afterwards. */
export class LegacyTableView extends TableView {
    constructor(...args: ConstructorParameters<typeof TableView>) {
        super(...args);
        this.restoreLegacyStyle();
    }

    update(node: ProseMirrorNode): boolean {
        if (node.type !== this.node.type) {
            return false;
        }
        // Remove previous source declarations so clearing/replacing them cannot leave stale styles.
        const previous = document.createElement("table");
        previous.style.cssText = this.node.attrs.legacy_style ?? "";
        for (const property of Array.from(previous.style)) {
            this.table.style.removeProperty(property);
        }
        if (!super.update(node)) {
            return false;
        }
        this.restoreLegacyStyle();
        return true;
    }

    private restoreLegacyStyle(): void {
        const style = this.node.attrs.legacy_style;
        if (typeof style === "string" && style) {
            this.table.style.cssText += `; ${style}`;
        }
    }
}
