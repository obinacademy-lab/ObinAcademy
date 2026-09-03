// Gates Phase 1's visitor tracking and Phase 2's lead-capture popups behind
// an actual choice — nothing non-essential runs until the visitor accepts
// or rejects. Other scripts read window.obinConsent()/wait via
// obin:consent-changed rather than duplicating this cookie logic.
(() => {
  function getConsent() {
    const match = document.cookie.split("; ").find((c) => c.startsWith("oa_consent="));
    return match ? match.split("=")[1] : null;
  }

  window.obinConsent = getConsent;

  function setConsent(value) {
    document.cookie = `oa_consent=${value}; max-age=${365 * 86400}; path=/; SameSite=Lax`;
    document.dispatchEvent(new CustomEvent("obin:consent-changed", { detail: { value } }));
  }

  const banner = document.querySelector("[data-consent-banner]");
  if (!banner) return;

  if (getConsent() === null) {
    banner.hidden = false;
  }

  banner.querySelector("[data-consent-accept]").addEventListener("click", () => {
    setConsent("accepted");
    banner.hidden = true;
  });
  banner.querySelector("[data-consent-reject]").addEventListener("click", () => {
    setConsent("rejected");
    banner.hidden = true;
  });
})();
