import { notify } from "@admin/components/toast";
import { assertAdminSave } from "@admin/lib/admin-api";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useMemo, useRef, useState } from "react";
import { committedLayout, createSaveQueue } from "../Widgets/save-queue";
import type { Group } from "../lib/widget-types";
export function useWidgetGroups(initialGroups: Group[], saveUrl: string) {
    const t = useAdminTranslation();
    const [groups, setGroups] = useState(initialGroups);
    const groupsRef = useRef(groups);
    groupsRef.current = groups;
    const committed = useRef(
        new Map(
            initialGroups.flatMap((area) =>
                area.items.map(
                    (item) => [item.key, structuredClone(item.data)] as const,
                ),
            ),
        ),
    );
    const { version } = usePage();
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState("");

    const [savedGroups, setSavedGroups] = useState(() =>
        JSON.stringify(initialGroups),
    );
    const dirty = JSON.stringify(groups) !== savedGroups;

    const queue = useMemo(
        () =>
            createSaveQueue<Group[]>({
                save: async (snapshot, signal) => {
                    const response = await axios.post(
                        saveUrl,
                        {
                            groups: snapshot.map((area) => ({
                                sidebar_id: area.id,
                                items: area.items.map((item) => ({
                                    ...item.data,
                                    id: item.id,
                                })),
                            })),
                        },
                        {
                            signal,
                            headers: {
                                "X-Inertia": "true",
                                "X-Inertia-Version": version ?? "",
                                Accept: "application/json",
                            },
                        },
                    );
                    assertAdminSave(response.data, "Widgets/Index");
                },
                onSaved: (snapshot) => {
                    setSavedGroups(JSON.stringify(snapshot));
                    setSaveError("");
                    notify(t("Saved"));
                },
                onError: (error) => {
                    const message = axios.isAxiosError(error)
                        ? error.response?.data?.message ||
                          t("Widget changes could not be saved.")
                        : error instanceof Error
                          ? error.message
                          : t("Widget changes could not be saved.");
                    setSaveError(message);
                    notify(message, true);
                },
                onState: setSaving,
            }),
        [saveUrl, version, t],
    );
    useEffect(() => () => queue.cancel(), [queue]);
    function applyGroups(next: Group[], autosave: boolean) {
        groupsRef.current = next;
        setGroups(next);
        if (!autosave) return;
        for (const item of next.flatMap((area) => area.items)) {
            if (!committed.current.has(item.key))
                committed.current.set(item.key, structuredClone(item.data));
        }
        queue.enqueue(committedLayout(next, committed.current));
    }

    return {
        groups,
        groupsRef,
        committed,
        saving,
        saveError,
        setSaveError,
        dirty,
        queue,
        applyGroups,
    };
}
