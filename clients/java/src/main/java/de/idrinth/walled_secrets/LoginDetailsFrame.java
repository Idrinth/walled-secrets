package de.idrinth.walled_secrets;

import java.io.IOException;
import javax.json.JsonObject;
import javax.swing.Box;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JTextArea;
import javax.swing.JTextField;
import javax.swing.Popup;
import javax.swing.PopupFactory;
import javax.swing.SwingWorker;

public class LoginDetailsFrame extends JFrame
{
    private final Request request;
    private final JTextField login;
    private final JTextField password;
    private final JTextArea note;
    private final String project;

    public LoginDetailsFrame(String project, SwingFactory factory, Request request)
    {
        super("Login Details | " + project);
        this.project = project;
        this.request = request;
        setDefaultCloseOperation(JFrame.DISPOSE_ON_CLOSE);
        Box wrapper = Box.createVerticalBox();
        Box loginBox = Box.createHorizontalBox();
        loginBox.add(new JLabel("Your Login"));
        this.login = new JTextField(55);
        loginBox.add(this.login);
        wrapper.add(loginBox);
        Box passwordBox = Box.createHorizontalBox();
        passwordBox.add(new JLabel("Your Password"));
        this.password = new JTextField(55);
        passwordBox.add(this.password);
        wrapper.add(passwordBox);
        Box noteBox = Box.createHorizontalBox();
        noteBox.add(new JLabel("Your Note"));
        this.note = new JTextArea(5, 55);
        noteBox.add(this.note);
        wrapper.add(noteBox);
        add(wrapper);
        pack();
    }
    public void display(boolean visible, String id, String master) {
        setVisible(visible);
        toFront();
        setTitle("Loading | Login Details | " + project);
        JFrame a = this;
        (new SwingWorker() {
            @Override
            protected Object doInBackground() throws Exception {
                try {
                    JsonObject obj = request.getLogin(id, master);
                    note.setText(obj.getString("note", ""));
                    login.setText(obj.getString("login", ""));
                    password.setText(obj.getString("pass", ""));
                    setTitle(obj.getString("public", "") + " | Login Details | " + project);
                } catch (IOException ex) {
                    Popup popup = PopupFactory.getSharedInstance().getPopup(a, new JLabel(ex.getMessage()), 0, 0);
                    popup.show();
                }
                return null;
            }        
        }).execute();
    }
}
