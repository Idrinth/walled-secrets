package de.idrinth.walled_secrets;

import java.awt.event.MouseEvent;
import java.awt.event.MouseListener;
import javax.swing.Box;
import javax.swing.JButton;
import javax.swing.JLabel;
import javax.swing.JTextField;
import javax.swing.JPanel;
import javax.swing.Popup;
import javax.swing.PopupFactory;

public class LoginPanel extends JPanel
{
    private final JTextField apikey;
    private final JTextField email;
    private final JTextField server;
    private final JTextField frequency;
    public LoginPanel(SwingFactory factory, Config config) {
        super();
        Box wrapper = Box.createVerticalBox();
        Box emailBox = Box.createHorizontalBox();
        emailBox.add(new JLabel("Your eMail"));
        this.email = new JTextField(55);
        emailBox.add(this.email);
        wrapper.add(emailBox);
        Box serverBox = Box.createHorizontalBox();
        serverBox.add(new JLabel("Your Server's Adress"));
        this.server = new JTextField(55);
        this.server.setText(config.getServer());
        serverBox.add(this.server);
        wrapper.add(serverBox);
        Box apikeyBox = Box.createHorizontalBox();
        apikeyBox.add(new JLabel("Your API-Key"));
        this.apikey = new JTextField(55);
        this.apikey.setText(config.getApikey());
        apikeyBox.add(this.apikey);
        wrapper.add(apikeyBox);
        Box frequencyBox = Box.createHorizontalBox();
        frequencyBox.add(new JLabel("Update every x Seconds"));
        this.frequency = new JTextField(55);
        this.frequency.setText(String.valueOf(config.getFrequency()));
        frequencyBox.add(this.frequency);
        wrapper.add(frequencyBox);
        JButton button = new JButton("Sign in");
        JPanel panel = this;
        button.addMouseListener(new MouseListener() {
            @Override
            public void mouseClicked(MouseEvent e) {
                try{
                    config.setEmail(email.getText());
                    config.setServer(server.getText());
                    config.setApikey(apikey.getText());
                    config.setFrequency(Integer.parseInt(frequency.getText(), 10));
                } catch (Exception ex) {
                    Popup popup = PopupFactory.getSharedInstance().getPopup(panel, new JLabel(ex.getMessage()), 0, 0);
                    popup.show();
                    try {
                        Thread.sleep(5000);
                    } catch (InterruptedException ex1) {
                    }
                    popup.hide();
                    return;
                }
                factory.showList();
            }
            @Override
            public void mousePressed(MouseEvent e) {}
            @Override
            public void mouseReleased(MouseEvent e) {}
            @Override
            public void mouseEntered(MouseEvent e) {}
            @Override
            public void mouseExited(MouseEvent e) {}
        });
        wrapper.add(button);
        add(wrapper);
    }
}
