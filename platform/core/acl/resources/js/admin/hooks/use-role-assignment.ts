import { notify } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useState } from "react";
import { createRoleRequests } from "../lib/role-requests";

import type { Props, Row } from "../lib/acl-types";
export function useRoleAssignment(
    items: Props["items"],
    assignRoleUrl: Props["assignRoleUrl"],
    roleOptions: NonNullable<Props["roleOptions"]>,
) {
    const t = useAdminTranslation();
    const { version } = usePage();
    const [roleValues, setRoleValues] = useState<Record<number, string>>({});
    const [roleBusy, setRoleBusy] = useState<Record<number, boolean>>({});
    const [roleErrors, setRoleErrors] = useState<Record<number, string>>({});
    const [roleRequests] = useState(createRoleRequests);
    useEffect(() => {
        roleRequests.cancel();
        setRoleValues({});
        setRoleErrors({});
        setRoleBusy({});
        return () => roleRequests.cancel();
    }, [items.data, assignRoleUrl, version, roleRequests]);
    async function assignRole(row: Row, value: string) {
        if (
            !assignRoleUrl ||
            !row.can_assign_role ||
            roleRequests.has(row.id) ||
            !roleOptions.some((option) => option.value === value)
        )
            return;
        const controller = roleRequests.begin(row.id);
        setRoleBusy((current) => ({ ...current, [row.id]: true }));
        setRoleErrors((current) => ({ ...current, [row.id]: "" }));
        try {
            const { data } = await axios.post(
                assignRoleUrl,
                { pk: row.id, value },
                {
                    signal: controller.signal,
                    headers: {
                        "X-Inertia": "true",
                        "X-Inertia-Version": version ?? "",
                        Accept: "application/json",
                    },
                },
            );
            if (!roleRequests.owns(row.id, controller)) return;
            assertRoleAssignment(data, t("Unable to assign role."));
            setRoleValues((current) => ({ ...current, [row.id]: value }));
            notify(t("Role updated"));
        } catch (error) {
            if (!roleRequests.owns(row.id, controller)) return;
            const message = axios.isAxiosError(error)
                ? error.response?.data?.message || t("Unable to assign role.")
                : error instanceof Error
                  ? error.message
                  : t("Unable to assign role.");
            setRoleErrors((current) => ({ ...current, [row.id]: message }));
            notify(message, true);
        } finally {
            const owned = roleRequests.owns(row.id, controller);
            roleRequests.finish(row.id, controller);
            if (owned)
                setRoleBusy((current) => ({ ...current, [row.id]: false }));
        }
    }
    return { roleValues, roleBusy, roleErrors, assignRole };
}
import { assertRoleAssignment } from "../lib/role-result";
