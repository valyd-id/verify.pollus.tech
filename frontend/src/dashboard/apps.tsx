import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from "react";
import { api, type App } from "./api";

type AppsState = {
  apps: App[];
  selected: App | null;
  ready: boolean;
  select: (id: number) => void;
  reload: () => Promise<App[]>;
};

const Ctx = createContext<AppsState>({ apps: [], selected: null, ready: false, select: () => {}, reload: async () => [] });
const SEL_KEY = "verify_selected_app";

export function AppsProvider({ children }: { children: ReactNode }) {
  const [apps, setApps] = useState<App[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(Number(localStorage.getItem(SEL_KEY)) || null);
  const [ready, setReady] = useState(false);

  const reload = useCallback(async () => {
    const r = await api.apps();
    const list = r.success && r.data ? r.data.apps : [];
    setApps(list);
    setReady(true);
    return list;
  }, []);

  useEffect(() => { reload(); }, [reload]);

  const select = (id: number) => { setSelectedId(id); localStorage.setItem(SEL_KEY, String(id)); };

  const selected = apps.find((a) => a.id === selectedId) ?? apps[0] ?? null;

  return <Ctx.Provider value={{ apps, selected, ready, select, reload }}>{children}</Ctx.Provider>;
}

export const useApps = () => useContext(Ctx);
