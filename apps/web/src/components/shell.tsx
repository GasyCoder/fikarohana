"use client";
import Link from "next/link";
import { Suspense } from "react";
import { usePathname } from "next/navigation";
import { useDiscovery } from "./discovery-context";
function NavigationLinks({ path = "" }: { path?: string }) {
  const { selected } = useDiscovery();
  return <nav aria-label="Main navigation"><Link href="/journals" aria-current={path.startsWith("/journals") ? "page" : undefined}>Browse journals</Link><Link href="/research" aria-current={path === "/research" || path === "/recommendations" ? "page" : undefined}>Describe research</Link><Link href="/compare" aria-current={path === "/compare" ? "page" : undefined}>Compare <span className="count">{selected.length}</span></Link></nav>;
}
function Navigation() { return <NavigationLinks path={usePathname()}/>; }
function CompareTray() {
  const path = usePathname(); const { selected } = useDiscovery();
  if (!selected.length || path === "/compare") return null;
  return <aside className="compare-tray" aria-label="Comparison selection"><span>{selected.length} of 5 journals selected</span><Link href="/compare" className="button">Compare journals →</Link></aside>;
}
export function Shell({ children }: { children: React.ReactNode }) {
  const { selectionError } = useDiscovery();
  return <><a className="skip-link" href="#main">Skip to content</a><header className="site-header"><div className="header-inner"><Link href="/" className="brand" aria-label="Fikarohana home"><span className="brand-mark" aria-hidden="true">f.</span>fikarohana</Link><Suspense fallback={<NavigationLinks/>}><Navigation/></Suspense></div></header><main id="main" className="page">{children}</main>{selectionError && <div className="selection-alert" role="alert">{selectionError}</div>}<Suspense fallback={null}><CompareTray/></Suspense><footer className="site-footer"><span>Fikarohana · Research, with direction.</span><span>Published metadata. Informed decisions.</span></footer></>;
}
