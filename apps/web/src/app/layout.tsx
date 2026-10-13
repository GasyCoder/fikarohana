import type { Metadata } from "next";
import type { ReactNode } from "react";
import { DiscoveryProvider } from "../components/discovery-context";
import { Shell } from "../components/shell";
import "./globals.css";
export const metadata: Metadata = { title: { default: "Fikarohana — Find your journal", template: "%s · Fikarohana" }, description: "Discover and compare published journal metadata. Research-led recommendations, with clear relevance explanations." };
export default function RootLayout({ children }: {
    children: ReactNode;
}) { return <html lang="en"><body><DiscoveryProvider><Shell>{children}</Shell></DiscoveryProvider></body></html>; }
