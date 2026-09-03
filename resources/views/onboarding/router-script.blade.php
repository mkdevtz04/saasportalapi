<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1-Command Router Setup — TrinetPay</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('onboarding._styles')
    <style>
        .one-command-card {
            background: linear-gradient(135deg, #1e1e2e 0%, #181825 100%);
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
            border: 1px solid #313244;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .one-command-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .one-command-title {
            font-size: 14px;
            font-weight: 700;
            color: #89b4fa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .copy-btn {
            background: #89b4fa;
            color: #11111b;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .copy-btn:hover { background: #b4befe; transform: translateY(-1px); }
        .command-code-wrap {
            background: #11111b;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #45475a;
            overflow-x: auto;
        }
        .command-code {
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            color: #a6e3a1;
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 1.6;
        }

        /* RodLink Progress Breakdown Styling */
        .progress-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .progress-box-header {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .progress-box-sub {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .progress-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .progress-step {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            padding: 14px 18px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .progress-step.active {
            background: #eff6ff;
            border-color: #93c5fd;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);
        }
        .progress-step.completed {
            background: #f0fdf4;
            border-color: #86efac;
        }
        .step-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .step-icon-wrap {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #64748b;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .progress-step.active .step-icon-wrap {
            background: #3b82f6;
            color: #ffffff;
        }
        .progress-step.completed .step-icon-wrap {
            background: #22c55e;
            color: #ffffff;
        }
        .step-text .title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .step-text .desc {
            font-size: 12px;
            color: #64748b;
        }
        .badge-status {
            font-size: 11px;
            font-weight: 700;
            text-transform: lowercase;
            padding: 4px 12px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #64748b;
        }
        .progress-step.active .badge-status {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .progress-step.completed .badge-status {
            background: #dcfce7;
            color: #15803d;
        }

        .success-banner {
            display: none;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
<div class="wizard-wrap">
    @include('onboarding._steps', ['current' => 1])

    <div class="card">
        <div class="card-header">
            <div class="step-icon"><i class="fa-solid fa-bolt"></i></div>
            <div>
                <h2>Connect Your MikroTik Router</h2>
                <p class="sub">Run this 1-line command in your WinBox terminal & boom — your device will be available!</p>
            </div>
        </div>

        <div id="success-banner" class="success-banner">
            <i class="fa-solid fa-circle-check fa-2x"></i>
            <div>
                <strong style="font-size:16px;">Router Successfully Provisioned!</strong>
                <p style="font-size:13px;margin:2px 0 0 0;opacity:0.9;">TrinetPay has finished configuring your router and verified the API connection.</p>
            </div>
        </div>

        <div class="one-command-card">
            <div class="one-command-header">
                <span class="one-command-title">
                    <i class="fa-solid fa-terminal"></i> WinBox Terminal Provision Command
                </span>
                <button class="copy-btn" onclick="copyCommand()">
                    <i class="fa-solid fa-copy"></i> Copy Command
                </button>
            </div>
            <div class="command-code-wrap">
                <div class="command-code" id="provision-command">/tool fetch url="{{ request()->getSchemeAndHttpHost() }}/provision/{{ $router->provision_token }}" dst-path=trinetpay-bootstrap.rsc check-certificate=no; :import trinetpay-bootstrap.rsc; /file remove trinetpay-bootstrap.rsc</div>
            </div>
        </div>

        <p style="font-size:13px;color:#64748b;margin-bottom:12px;">
            <i class="fa-solid fa-circle-info"></i> Open WinBox &rarr; <strong>New Terminal</strong> &rarr; Right Click &rarr; <strong>Paste</strong> &rarr; Hit Enter.
        </p>

        <!-- RodLink Progress Breakdown List -->
        <div class="progress-box">
            <div class="progress-box-header">Setup Progress</div>
            <div class="progress-box-sub">The router is being configured. This takes just a few seconds.</div>

            <div class="progress-list">
                <!-- Step 1 -->
                <div class="progress-step {{ $router->provision_status === 'pending' ? 'active' : 'completed' }}" id="step-1">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-1-icon">
                            <i class="fa-solid {{ $router->provision_status === 'pending' ? 'fa-spinner fa-spin' : 'fa-check' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Connecting to Router</div>
                            <div class="desc">Downloading the one-time bootstrap script.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-1-badge">
                        {{ $router->provision_status === 'pending' ? 'waiting' : 'completed' }}
                    </span>
                </div>

                <!-- Step 2 -->
                <div class="progress-step {{ $router->provision_status === 'script_downloaded' ? 'active' : ($router->provision_status === 'completed' ? 'completed' : '') }}" id="step-2">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-2-icon">
                            <i class="fa-solid {{ $router->provision_status === 'script_downloaded' ? 'fa-spinner fa-spin' : ($router->provision_status === 'completed' ? 'fa-check' : 'fa-floppy-disk') }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Creating Backup</div>
                            <div class="desc">Saving an encrypted backup before management changes.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-2-badge">
                        {{ $router->provision_status === 'script_downloaded' ? 'in progress' : ($router->provision_status === 'completed' ? 'completed' : 'pending') }}
                    </span>
                </div>

                <!-- Step 3 -->
                <div class="progress-step {{ $router->provision_status === 'completed' ? 'completed' : '' }}" id="step-3">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-3-icon">
                            <i class="fa-solid {{ $router->provision_status === 'completed' ? 'fa-check' : 'fa-route' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Configuring Hotspot &amp; Walled Garden</div>
                            <div class="desc">Setting up portal redirect and mobile money gateways.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-3-badge">
                        {{ $router->provision_status === 'completed' ? 'completed' : 'pending' }}
                    </span>
                </div>

                <!-- Step 4 -->
                <div class="progress-step {{ $router->provision_status === 'completed' ? 'completed' : '' }}" id="step-4">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-4-icon">
                            <i class="fa-solid {{ $router->provision_status === 'completed' ? 'fa-check' : 'fa-lock' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Configuring API</div>
                            <div class="desc">Restricting RouterOS API access to the VPS server.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-4-badge">
                        {{ $router->provision_status === 'completed' ? 'completed' : 'pending' }}
                    </span>
                </div>

                <!-- Step 5 -->
                <div class="progress-step {{ $router->provision_status === 'completed' ? 'completed' : '' }}" id="step-5">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-5-icon">
                            <i class="fa-solid {{ $router->provision_status === 'completed' ? 'fa-check' : 'fa-network-wired' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Verifying Setup</div>
                            <div class="desc">Verifying the secure API tunnel &amp; handshake from the VPS.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-5-badge">
                        {{ $router->provision_status === 'completed' ? 'completed' : 'pending' }}
                    </span>
                </div>
            </div>
        </div>

        <a href="{{ route('onboarding.packages') }}" id="btn-continue" class="btn-primary" style="display:block;text-align:center;text-decoration:none;margin-top:24px;">
            Continue to Step 2: Packages &rarr;
        </a>
    </div>
</div>

<script>
function copyCommand() {
    const text = document.getElementById('provision-command').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        setTimeout(() => {
            btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy Command';
        }, 2500);
    });
}

// Live Status Polling Loop
const token = "{{ $router->provision_token }}";
let isCompleted = "{{ $router->provision_status }}" === "completed";

if (isCompleted) {
    showCompletedUI();
} else {
    const pollInterval = setInterval(() => {
        fetch(`/provision/${token}/status`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateStatusUI(data.status);
                    if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        showCompletedUI();
                    }
                }
            })
            .catch(err => console.log('Polling error:', err));
    }, 1500);
}

function updateStatusUI(status) {
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const step3 = document.getElementById('step-3');
    const step4 = document.getElementById('step-4');
    const step5 = document.getElementById('step-5');

    if (status === 'script_downloaded') {
        step1.className = 'progress-step completed';
        document.getElementById('step-1-icon').innerHTML = '<i class="fa-solid fa-check"></i>';
        document.getElementById('step-1-badge').innerText = 'completed';

        step2.className = 'progress-step active';
        document.getElementById('step-2-icon').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        document.getElementById('step-2-badge').innerText = 'in progress';
    } else if (status === 'completed') {
        [step1, step2, step3, step4, step5].forEach((s, idx) => {
            s.className = 'progress-step completed';
            document.getElementById(`step-${idx+1}-icon`).innerHTML = '<i class="fa-solid fa-check"></i>';
            document.getElementById(`step-${idx+1}-badge`).innerText = 'completed';
        });
    }
}

function showCompletedUI() {
    updateStatusUI('completed');
    const banner = document.getElementById('success-banner');
    if (banner) banner.style.display = 'flex';
}
</script>
</body>
</html>
