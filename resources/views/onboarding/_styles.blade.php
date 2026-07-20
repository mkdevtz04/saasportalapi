<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; min-height: 100vh; padding: 32px 16px 48px; color: #111; }
.wizard-wrap { max-width: 680px; margin: 0 auto; }
.steps { display: flex; align-items: center; gap: 0; margin-bottom: 28px; }
.step { display: flex; align-items: center; gap: 8px; flex: 1; }
.step-num { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; flex-shrink: 0; }
.step.done .step-num { background: #0066cc; color: #fff; }
.step.active .step-num { background: #0066cc; color: #fff; box-shadow: 0 0 0 4px #bfdbfe; }
.step.upcoming .step-num { background: #e2e8f0; color: #94a3b8; }
.step-label { font-size: 13px; font-weight: 600; color: #334155; }
.step.upcoming .step-label { color: #94a3b8; }
.step-line { flex: 1; height: 2px; background: #e2e8f0; margin: 0 8px; }
.step-line.done { background: #0066cc; }
.brand-top { text-align: center; margin-bottom: 24px; }
.brand-top a { font-size: 22px; font-weight: 800; color: #0066cc; text-decoration: none; }
.card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 36px; }
.card-header { display: flex; gap: 16px; align-items: flex-start; margin-bottom: 28px; }
.step-icon { font-size: 36px; line-height: 1; flex-shrink: 0; }
.card-header h2 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.card-header .sub { font-size: 14px; color: #64748b; line-height: 1.5; }
.field { margin-bottom: 18px; }
.field label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
.hint-inline { font-weight: 400; color: #94a3b8; font-size: 12px; }
.field input[type=text],
.field input[type=password],
.field input[type=number],
.field input[type=email] { width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 15px; color: #111; outline: none; background: #f8fafc; transition: border-color .2s; }
.field input:focus { border-color: #0066cc; background: #fff; }
.hint { font-size: 12px; color: #94a3b8; margin-top: 5px; display: block; }
.field-row { display: flex; gap: 16px; }
.field-row .field { flex: 1; }
.btn-primary { display: block; width: 100%; padding: 14px; background: #0066cc; color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background .2s; }
.btn-primary:hover { background: #0055aa; }
.btn-test { padding: 10px 20px; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; color: #334155; }
.btn-test:hover:not(:disabled) { border-color: #0066cc; color: #0066cc; }
.btn-test:disabled { opacity: .5; cursor: not-allowed; }
.test-row { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
.test-ok { color: #166534; font-size: 13px; font-weight: 600; }
.test-fail { color: #991b1b; font-size: 13px; font-weight: 600; }
.info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #1e40af; line-height: 1.6; }
.info-box code { background: #dbeafe; padding: 1px 6px; border-radius: 3px; font-family: monospace; }
.alert-error { background: #fff5f5; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #991b1b; }
.alert-error div + div { margin-top: 4px; }
</style>
