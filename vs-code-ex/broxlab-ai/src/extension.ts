import * as vscode from 'vscode';
import { BroxLabViewProvider } from './BroxLabViewProvider';

export function activate(context: vscode.ExtensionContext) {
    const provider = new BroxLabViewProvider(context.extensionUri, context);

    context.subscriptions.push(
        vscode.window.registerWebviewViewProvider(BroxLabViewProvider.viewType, provider)
    );

    context.subscriptions.push(
        vscode.commands.registerCommand('broxlab.openSettings', () => {
            vscode.commands.executeCommand('broxlab-activitybar.focus');
            // Can add logic to auto-switch to settings tab if desired
        })
    );

    context.subscriptions.push(
        vscode.commands.registerCommand('broxlab.moveToSecondarySideBar', () => {
            vscode.commands.executeCommand('workbench.action.moveFocusedView');
            vscode.window.showInformationMessage('BroxLab AI: Use the quick pick to select "Secondary Side Bar", or drag the BroxLab icon to the right side of the screen!');
        })
    );
}

export function deactivate() { }
