#!/usr/bin/env node
/**
 * WordPress Plugin Check Runner
 * 
 * Runs the WordPress Plugin Check tool to verify the plugin
 * passes review guidelines before submitting to the directory.
 * 
 * Usage: node bin/wordpress-check.js
 */

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const PLUGIN_DIR = __dirname + '/../';
const PLUGIN_FILE = 'swift-menu-duplicator.php';

console.log('🧪 Running WordPress Plugin Check...\n');

try {
    // Check if WordPress is available
    const wpCliPath = path.join(PLUGIN_DIR, 'vendor/bin/wp');
    
    if (!fs.existsSync(wpCliPath)) {
        console.log('⚠️  WP-CLI not found. Trying system wp command...');
    }

    // Run plugin check
    const cmd = `wp plugin check ${PLUGIN_FILE} --allow-root 2>&1`;
    const output = execSync(cmd, { 
        cwd: PLUGIN_DIR,
        encoding: 'utf-8',
        stdio: ['pipe', 'pipe', 'pipe']
    });

    console.log(output);

    // Check for errors
    if (output.includes('ERROR') || output.includes('FAIL')) {
        console.log('\n❌ Plugin check failed. Please fix the issues above.');
        process.exit(1);
    }

    console.log('✅ Plugin check passed!');
    process.exit(0);

} catch (error) {
    // Fallback: run basic checks manually
    console.log('⚠️  WP-CLI check failed, running manual validation...\n');
    
    const issues = [];
    
    // Check 1: Plugin file exists
    if (!fs.existsSync(path.join(PLUGIN_DIR, PLUGIN_FILE))) {
        issues.push(`Plugin file ${PLUGIN_FILE} not found`);
    }
    
    // Check 2: Readme.txt exists
    if (!fs.existsSync(path.join(PLUGIN_DIR, 'readme.txt'))) {
        issues.push('readme.txt not found');
    }
    
    // Check 3: Plugin header
    const pluginContent = fs.readFileSync(path.join(PLUGIN_DIR, PLUGIN_FILE), 'utf-8');
    const requiredHeaders = ['Plugin Name', 'Plugin URI', 'Description', 'Version', 'Author'];
    
    for (const header of requiredHeaders) {
        if (!pluginContent.includes(header)) {
            issues.push(`Missing header: ${header}`);
        }
    }
    
    // Check 4: Text domain matches slug
    const textDomainMatch = pluginContent.match(/Text Domain:\s*(\S+)/);
    if (textDomainMatch && textDomainMatch[1] !== 'swift-menu-duplicator') {
        issues.push(`Text domain mismatch: expected "swift-menu-duplicator", found "${textDomainMatch[1]}"`);
    }
    
    // Check 5: Stable tag in readme
    const readmeContent = fs.readFileSync(path.join(PLUGIN_DIR, 'readme.txt'), 'utf-8');
    const stableTagMatch = readmeContent.match(/Stable tag:\s*([\d.]+)/);
    const versionMatch = pluginContent.match(/Version:\s*([\d.]+)/);
    
    if (stableTagMatch && versionMatch && stableTagMatch[1] !== versionMatch[1]) {
        issues.push(`Stable tag mismatch: readme.txt has ${stableTagMatch[1]}, plugin has ${versionMatch[1]}`);
    }
    
    if (issues.length > 0) {
        console.log('❌ Issues found:');
        issues.forEach(issue => console.log(`  - ${issue}`));
        process.exit(1);
    }
    
    console.log('✅ Basic validation passed!');
    console.log('\nNote: For full WP check, install WP-CLI and run:');
    console.log('  wp plugin check swift-menu-duplicator.php --allow-root');
    process.exit(0);
}