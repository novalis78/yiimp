# YIIMP Mining Pool - AI Developer Guide

> **Purpose**: This guide captures institutional knowledge from debugging sessions and provides
> a roadmap for future AI assistants working on the Marscoin YIIMP mining pool.

## Quick Reference

| Component | Location | Port |
|-----------|----------|------|
| YIIMP Web | `/var/www/mining-mars/yiimp/` | 80/443 |
| Stratum Server | `/var/www/mining-mars/yiimp/stratum/` | 3433 |
| Marscoind | `/home/marscoin-28/src/` | 8338 (P2P), 9432 (RPC) |
| Marscoin Data | `/tmp/.marscoin/` | - |
| Rescue Miner | `/home/claude/rescue-miner/` | - |
| Logs | `/var/yaamp/config/*.log` | - |

## Critical Configuration Issues (Lessons Learned)

### 1. The `auto_ready` Flag Problem

**Symptom**: Stratum crashes every 2 minutes with "dead lock, exiting..."

**Root Cause**: The coins table has two critical flags:
- `enable` - Whether the coin is enabled
- `auto_ready` - Whether the coin should be loaded by stratum

The stratum server queries:
```sql
SELECT ... FROM coins WHERE enable AND auto_ready AND algo='scrypt'
```

If `auto_ready=0`, the coin won't load, no jobs will be created, and the deadlock monitor
(120 second timeout) will kill the process.

**Fix**:
```sql
UPDATE coins SET auto_ready=1 WHERE symbol='MARS';
```

**Location**: `/var/www/mining-mars/yiimp/stratum/db.cpp:197`

### 2. The `conf_folder` Path Problem

**Symptom**: "unable to find the wallet for coinid X" errors

**Root Cause**: The `conf_folder` field in the coins table must match the actual
marscoind data directory. Common mistake: assuming `/root/.marscoin` when it's
actually `/tmp/.marscoin`.

**Fix**:
```sql
UPDATE coins SET conf_folder='/tmp/.marscoin' WHERE symbol='MARS';
```

**Verification**: Check where marscoind is actually running:
```bash
ps aux | grep marscoind
# Look for -datadir= parameter
```

### 3. Stratum Dead Lock Monitor

**Location**: `/var/www/mining-mars/yiimp/stratum/stratum.cpp:441-452`

```cpp
void *monitor_thread(void *p)
{
    while(!g_exiting)
    {
        sleep(120);
        if(g_last_broadcasted + YAAMP_MAXJOBDELAY < time(NULL))
        {
            g_exiting = true;
            stratumlogdate("%s dead lock, exiting...\n", g_stratum_algo);
            exit(1);
        }
    }
}
```

The `YAAMP_MAXJOBDELAY` is defined as `2*60` (120 seconds) in `stratum.h:35`.

If no job is broadcasted within 120 seconds, the stratum exits. This is a safety
mechanism but causes crash loops when misconfigured.

## Database Schema (Key Tables)

### coins table
| Column | Purpose |
|--------|---------|
| `id` | Primary key (MARS = 7) |
| `symbol` | Coin ticker |
| `enable` | Whether coin is enabled |
| `auto_ready` | **CRITICAL** - Must be 1 for stratum to load |
| `conf_folder` | Path to coin data directory |
| `rpchost`, `rpcport` | RPC connection |
| `rpcuser`, `rpcpasswd` | RPC credentials |
| `algo` | Mining algorithm (scrypt for MARS) |

### blocks table
Stores found blocks with status tracking.

### workers table
Connected miners and their stats.

## Stratum Architecture

```
main()
  -> yaamp_create_mutex()
  -> db_init_main_coind()          # Loads coins from DB (requires auto_ready=1)
  -> job_init_coind()               # Initializes job generation
  -> job_create_last()              # Creates initial jobs
  -> monitor_thread()               # Watchdog (kills if no jobs in 120s)
  -> stratum_listen_thread()        # Accepts miner connections
```

**Key Files**:
- `stratum.cpp` - Main entry point, threading
- `db.cpp` - Database queries
- `job.cpp` - Block template fetching, job creation
- `coind.cpp` - Coin daemon RPC communication
- `client.cpp` - Miner connection handling

## Frontend Structure

```
/var/www/mining-mars/yiimp/
├── web/
│   ├── yaamp/
│   │   ├── core/
│   │   ├── models/
│   │   ├── modules/
│   │   │   └── site/
│   │   │       └── views/       # Main templates
│   │   └── ui/
│   │       └── images/
│   └── index.php
├── stratum/                      # C++ stratum server
└── sql/                          # Database schemas
```

## Known Issues to Fix

### 1. BTC-Centric Language
Many strings reference "Bitcoin" or use BTC terminology. Search for:
- "Bitcoin", "BTC", "satoshi"
- Generic mining pool language that should be Mars-themed

### 2. Hardcoded Values
The stratum has many hardcoded assumptions about multi-coin pools.
For a single-coin (Marscoin) pool, this adds unnecessary complexity.

### 3. Error Handling
The "unable to find wallet" error is cryptic. Better logging would help:
- Log the actual path being checked
- Log RPC connection attempts
- Log conf_folder value being used

## Rescue Miner System

Located at `/home/claude/rescue-miner/`, this system:

1. Monitors mempool for stuck transactions (>15 minutes)
2. Rents Scrypt hashpower from MiningRigRentals
3. Points rented rigs at the local stratum (mining-mars.com:3433)
4. Clears the mempool by mining blocks

**Configuration**: `config.py`
**Systemd Service**: `rescue-miner.service`

## Useful Commands

```bash
# Check stratum status
systemctl status yiimp

# View stratum logs
tail -f /var/yaamp/config/scrypt.log

# Check marscoind
/home/marscoin-28/src/marscoin-cli -datadir=/tmp/.marscoin getblockchaininfo
/home/marscoin-28/src/marscoin-cli -datadir=/tmp/.marscoin getmempoolinfo
/home/marscoin-28/src/marscoin-cli -datadir=/tmp/.marscoin getpeerinfo

# Database access
mysql -u root yaamp

# Restart stratum
systemctl restart yiimp
```

## Security Notes

- UFW firewall enabled (ports 22, 80, 443, 3433, 8338)
- fail2ban protects SSH (3 attempts, 24h ban)
- SSH key-only authentication
- RPC credentials in `/tmp/.marscoin/marscoin.conf`
- MRR API keys in `/home/claude/rescue-miner/config.py`

## Testing Framework

A Puppeteer-based test suite is available at `/home/claude/rescue-miner/tests/`:

```bash
cd /home/claude/rescue-miner/tests
npm install
npm test              # Run functional tests
npm run test:screenshot  # Capture screenshots
```

**Tests Include:**
- Homepage loads correctly
- No BTC-centric language (Mars branding check)
- Stratum command generator works
- Pool statistics load via AJAX
- Mars theme CSS applied
- Mobile responsiveness

## Secret Detection (Gitleaks)

A gitleaks configuration is provided at `/home/claude/rescue-miner/.gitleaks.toml`:

```bash
# Install gitleaks
brew install gitleaks  # macOS
# or download from https://github.com/gitleaks/gitleaks

# Run scan
gitleaks detect --source /var/www/mining-mars/yiimp --config /home/claude/rescue-miner/.gitleaks.toml
```

**Custom Rules Detect:**
- Marscoin RPC passwords
- MiningRigRentals API keys
- MySQL passwords
- Exchange API keys
- Wallet private keys
- Stratum passwords

## Files Changed in This Session

| File | Change |
|------|--------|
| `/var/www/.../ui/lib/pageheader.php` | Updated meta tags to Mars-themed |
| `/var/www/.../ui/main.php` | Removed BTC keywords |
| `/var/www/.../modules/site/index.php` | Replaced BTC warning with Mars wallet info |
| `/var/www/.../modules/site/mining.php` | Changed mBTC to MARS |
| `/var/www/.../ui/css/main.css` | Added mars-theme.css import |
| `/var/www/.../ui/css/mars-theme.css` | New Mars color theme |

## Improvement Roadmap

### Completed
- Mars-themed CSS styling
- BTC language replaced with Mars branding
- Puppeteer test framework
- Gitleaks secret detection
- AI developer guide

### Remaining
1. **Frontend Modernization**
   - Add real-time hashrate graphs (WebSocket)
   - Improve mining statistics visualization
   - Add block explorer integration

2. **Code Robustness**
   - Better error messages with actual values logged
   - Graceful handling of RPC failures
   - Health check endpoints

3. **Testing**
   - Integration tests for stratum
   - Monitoring/alerting system

4. **Documentation**
   - API documentation
   - User guides
   - Deployment procedures
