import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from "react";
import { api } from "./api";

type BalanceState = {
  balance: number;
  currency: string;
  ready: boolean;
  reload: () => Promise<void>;
};

const Ctx = createContext<BalanceState>({ balance: 0, currency: "USD", ready: false, reload: async () => {} });

const SYMBOLS: Record<string, string> = { USD: "$", EUR: "€", GBP: "£" };

/** The symbol for a currency code (empty string if unknown). */
export const currencySymbol = (currency = "USD") => SYMBOLS[currency] ?? "";

/** Format an amount with its currency symbol, always 2 decimals. */
export function formatMoney(amount: number, currency = "USD"): string {
  return `${currencySymbol(currency)}${amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function BalanceProvider({ children }: { children: ReactNode }) {
  const [balance, setBalance] = useState(0);
  const [currency, setCurrency] = useState("USD");
  const [ready, setReady] = useState(false);

  const reload = useCallback(async () => {
    const r = await api.balance();
    if (r.success && r.data) {
      setBalance(r.data.balance);
      setCurrency(r.data.currency);
    }
    setReady(true);
  }, []);

  useEffect(() => { reload(); }, [reload]);

  return <Ctx.Provider value={{ balance, currency, ready, reload }}>{children}</Ctx.Provider>;
}

export const useBalance = () => useContext(Ctx);
